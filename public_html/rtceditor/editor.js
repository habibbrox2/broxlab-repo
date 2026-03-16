/**
 * Rich Text Editor - Vanilla JavaScript
 * Production-Ready Core Module
 * Compatible with PHP & Twig projects
 * 
 * @version 2.0.0
 * @author Hr Habib
 * @license MIT
 */

// =============================================================================
// GLOBAL DEBUG CONFIGURATION
// =============================================================================

if (typeof window.RTE_DEBUG === 'undefined') {
    window.RTE_DEBUG = false;
}

// helper API in case someone wants to toggle logging programmatically
window.RTE_enableDebug = function (flag) {
    window.RTE_DEBUG = !!flag;
};
window.RTE_isDebug = function () {
    return !!window.RTE_DEBUG;
};

// -----------------------------------------------------------------------------
// Convenience helpers used by the editor (e.g. AI enhance button)
// These use the global message handler if available; otherwise fall back to
// simple console/toast output. The original names were `appendLoadingToast`
// and `removeLoadingToast` so that the action handler could remain unchanged.
// -----------------------------------------------------------------------------
window.appendLoadingToast = function (message = 'Loading...') {
    if (window.MessageHandler && typeof window.MessageHandler.showLoading === 'function') {
        window.MessageHandler.showLoading(message);
    } else if (typeof window.showToast === 'function') {
        // duration=0 for manual dismiss
        window.showToast(message, 'info', 0);
    } else {
        console.log('[RTE] loading:', message);
    }
};
window.removeLoadingToast = function () {
    if (window.MessageHandler && typeof window.MessageHandler.hideLoading === 'function') {
        window.MessageHandler.hideLoading();
    }
};

/**
 * Enhanced debug logging with category and timestamp
 * @param {string} category - Log category (e.g., 'color', 'selection')
 * @param {...any} args - Arguments to log
 */
window.RTE_debugLog = function (category, ...args) {
    if (window.RTE_DEBUG) {
        const timestamp = new Date().toLocaleTimeString('en-US', {
            hour12: false,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            fractionalSecondDigits: 3
        });
        console['log'](`[RTE:${category}:${timestamp}]`, ...args);
    }
};

// =============================================================================
// MODULE DEFINITION CHECK
// =============================================================================

if (typeof _RTE_shouldDefine === 'undefined') {
    var _RTE_shouldDefine = (typeof window === 'undefined' || !window.RichTextEditor);
}

if (_RTE_shouldDefine) {

    // =============================================================================
    // RICH TEXT EDITOR CLASS
    // =============================================================================

    class RichTextEditor {
        /**
         * Initialize Rich Text Editor instance
         * @param {string} editorId - ID of the editor element (without wrapper suffix)
         */
        constructor(editorId = 'richTextEditor', options = {}) {
            // allow enabling debug via instance config (affects all instances)
            if (options.debug) {
                window.RTE_DEBUG = true;
            }

            if (!options.skipHelperCheck && !RichTextEditor.isHelpersReady()) {
                const missingHelpers = RichTextEditor.getMissingHelpers();
                console.warn(
                    `RTE: Editor "${editorId}" initialized before helpers loaded. ` +
                    `Call await RichTextEditor.loadHelpers() first, or use RichTextEditor.create().` +
                    (missingHelpers.length ? ` Missing helpers: ${missingHelpers.join(', ')}` : '')
                );
            }
            // DOM references
            this.editorId = editorId;
            this.editor = document.getElementById(editorId);
            if (this.editor) {
                this.editor.setAttribute('contenteditable', 'true');

                // ensure future mutations don't accidentally disable editing
                try {
                    const mo = new MutationObserver(muts => {
                        muts.forEach(m => {
                            if (m.type === 'attributes' && m.attributeName === 'contenteditable') {
                                const val = this.editor.getAttribute('contenteditable');
                                if (val !== 'true') {
                                    console.warn('RTE: contenteditable changed to', val, 'restoring to true');
                                    this.editor.setAttribute('contenteditable', 'true');
                                }
                            }
                        });
                    });
                    mo.observe(this.editor, { attributes: true, attributeFilter: ['contenteditable'] });
                    this._contentEditableObserver = mo;
                } catch (err) {
                    // ignore if observer not supported
                }
            }
            this.wrapper = document.getElementById(`${editorId}-wrapper`);
            this.toolbar = document.getElementById(`${editorId}-toolbar`);
            this.sourceView = document.getElementById(`${editorId}-source`);
            this.historyView = document.getElementById(`${editorId}-history`);
            this.hiddenInput = document.getElementById(`${editorId}-input`);

            // Validate required elements
            if (!this.editor || !this.wrapper || !this.toolbar || !this.hiddenInput) {
                console.error(`RichTextEditor: Missing required elements for ${editorId}`);
                return;
            }

            // State management
            this.currentView = 'compose';
            this.isRTL = this.wrapper.dataset.rtl === 'true';
            this._destroyed = false;

            // History management
            this.history = [];
            this.historyIndex = -1;
            this.maxHistorySize = 50;
            this.historyDelay = 500;

            // Timers and timeouts
            this.historyTimeout = null;
            this.selectionSyncTimeout = null;
            this._selectionChangeTimer = null;
            this._boundSelectionChangeHandler = null;

            // Selection storage
            this._savedRange = null;
            this._savedColorRange = null;

            // Selection state flags
            this._selectionFinal = false;
            this._inIMEComposition = false;
            this._finalizationInProgress = false;
            this._finalizePending = false;

            // Image and media handling
            this._pendingImageData = null;
            this._pendingImageFile = null;
            this._pendingImagePreviewUrl = null;

            // Editor sizing state
            this._autoGrowEnabled = true;
            this._autoGrowMinHeight = 200;
            this._autoGrowMaxHeight = 600;
            this._manualHeightPx = 0;
            this._pointerDownHeight = 0;
            this._manualResizeLocked = false;
            this._lastKnownEditorHeight = 0;
            this._resizeObserver = null;
            this._isAutoSizing = false;
            this._cmInstance = null;
            this._selectionDebug = false;
            this._lastModalTrigger = null;

            // Initialize editor
            this.init();
        }

        // =========================================================================
        // INITIALIZATION
        // =========================================================================

        /**
         * Initialize the editor
         * Sets up event listeners and initial state
         */
        init() {
            // if AI assistant support isn't loaded, remove the toolbar button so
            // users don't click it and see a console warning. This covers pages
            // where the editor is used outside of the admin panel.
            if (typeof window.adminAssistantRewrite !== 'function' && this.toolbar) {
                const aiBtn = this.toolbar.querySelector('[data-action="aiEnhance"]');
                if (aiBtn) aiBtn.remove();
            }

            // Set initial content (sanitized via setContent)
            if (this.hiddenInput.value) {
                try {
                    this.setContent(this.hiddenInput.value);
                } catch (e) {
                    // Fail closed: never inject raw initial HTML when sanitization fails.
                    console.warn('setContent failed during init; applying safe fallback content:', e);
                    try {
                        if (typeof this.sanitizeHTML === 'function') {
                            const safeHtml = this.sanitizeHTML(this.hiddenInput.value);
                            this.editor.innerHTML = safeHtml || '<p><br></p>';
                        } else {
                            this.editor.innerHTML = '<p><br></p>';
                        }
                    } catch (sanitizeErr) {
                        console.warn('sanitize fallback failed during init:', sanitizeErr);
                        this.editor.innerHTML = '<p><br></p>';
                    }
                }
            }

            // Initialize history
            this.saveToHistory();

            // Setup core event handlers
            this.setupEditorEvents();
            this.setupAutoGrow();
            this._applyDisabledButtonsConfig();
            this.setupToolbarEvents();
            this.setupColorPickerLabels();
            this.setupKeyboardShortcuts();
            this.setupModals();

            // Setup additional handlers (from helper modules)
            this._setupHelperHandlers();

            // Initial UI sync
            if (this.wrapper) {
                this.wrapper.classList.remove('view-source', 'view-history');
                this.wrapper.classList.add('view-compose');
            }
            this.updateButtonStates();
            this.syncHeadingSelect();
            this.showSelectionDebug(false);

            // apply default font/size if none selected
            const fontSel = this.toolbar.querySelector('.rte-font-select');
            if (fontSel && !fontSel.value) {
                fontSel.value = 'Times New Roman';
            }
            const sizeSel = this.toolbar.querySelector('.rte-font-size-select');
            if (sizeSel && !sizeSel.value) {
                sizeSel.value = '12px';
            }

            this._setupTouchEvents();
            this._setupIMEEvents();

            // Dispatch ready event
            this._dispatchReadyEvent();

            window.RTE_debugLog('init', `Rich Text Editor initialized: ${this.editorId}`);
        }

        /**
         * Setup editor auto-grow and manual resize tracking
         */
        setupAutoGrow() {
            if (!this.editor || !this.wrapper) return;

            const autoGrowAttr = String(this.wrapper.dataset.autoGrow || '').trim().toLowerCase();
            this._autoGrowEnabled = autoGrowAttr !== 'false';
            this._autoGrowMinHeight = this._resolveHeightValue(this.wrapper.dataset.minHeight, 120);
            this._autoGrowMaxHeight = this._resolveHeightValue(this.wrapper.dataset.maxHeight, 2000);

            if (this._autoGrowMaxHeight < this._autoGrowMinHeight) {
                this._autoGrowMaxHeight = this._autoGrowMinHeight;
            }

            this.editor.style.minHeight = `${this._autoGrowMinHeight}px`;
            this.editor.style.maxHeight = `${this._autoGrowMaxHeight}px`;
            this.autoGrowEditorHeight({ force: true });
            this._setupResizeObserver();
        }

        /**
         * Auto-size the editable area by content height
         * @param {Object} options
         * @param {boolean} options.force - Ignore auto-grow disable flag
         */
        autoGrowEditorHeight(options = {}) {
            if (!this.editor) return;
            if (!this._autoGrowEnabled && !options.force) return;

            const minHeight = this._autoGrowMinHeight || 120;
            const maxHeight = this._autoGrowMaxHeight || 2000;
            if (this._manualResizeLocked && !options.overrideManual) {
                const lockedHeight = Math.max(minHeight, Math.min(maxHeight, this._manualHeightPx || minHeight));
                if (lockedHeight > 0) {
                    this._isAutoSizing = true;
                    this.editor.style.height = `${Math.round(lockedHeight)}px`;
                    this._lastKnownEditorHeight = Math.round(lockedHeight);
                    requestAnimationFrame(() => { this._isAutoSizing = false; });
                }
                return;
            }
            const previousHeight = this.editor.style.height;

            this._isAutoSizing = true;
            this.editor.style.height = 'auto';
            let desiredHeight = this.editor.scrollHeight || minHeight;
            desiredHeight = Math.max(desiredHeight, minHeight);
            desiredHeight = Math.min(desiredHeight, maxHeight);

            if (!options.force && this._manualHeightPx > 0) {
                desiredHeight = Math.max(desiredHeight, this._manualHeightPx);
            }

            this.editor.style.height = `${Math.round(desiredHeight)}px`;
            this._lastKnownEditorHeight = Math.round(desiredHeight);
            requestAnimationFrame(() => { this._isAutoSizing = false; });

            // If scrollHeight is not measurable yet, revert to previous style.
            if (!this.editor.scrollHeight && previousHeight) {
                this.editor.style.height = previousHeight;
            }
        }

        /**
         * Observe editor resize and keep manual resize preference locked.
         * @private
         */
        _setupResizeObserver() {
            if (!this.editor || this._resizeObserver || typeof ResizeObserver === 'undefined') return;

            this._lastKnownEditorHeight = Math.round(this.editor.offsetHeight || 0);
            this._resizeObserver = new ResizeObserver((entries) => {
                if (!entries || entries.length === 0 || !this.editor || this._destroyed) return;
                if (this._isAutoSizing) return;

                const entry = entries[entries.length - 1];
                const observedHeight = Math.round(entry.contentRect?.height || this.editor.offsetHeight || 0);
                if (!observedHeight) return;

                const minHeight = this._autoGrowMinHeight || 120;
                const maxHeight = this._autoGrowMaxHeight || 2000;
                const clamped = Math.max(minHeight, Math.min(maxHeight, observedHeight));
                if (Math.abs(clamped - (this._lastKnownEditorHeight || 0)) < 2) return;

                this._manualHeightPx = clamped;
                this._manualResizeLocked = true;
                this._lastKnownEditorHeight = clamped;
                window.RTE_debugLog('resize', `Manual resize observed: ${clamped}px`);

                if (Math.abs(clamped - observedHeight) >= 2) {
                    this._isAutoSizing = true;
                    this.editor.style.height = `${clamped}px`;
                    requestAnimationFrame(() => { this._isAutoSizing = false; });
                }
            });

            this._resizeObserver.observe(this.editor);
        }

        /**
         * Track manual editor resize from CSS handle drag
         * @private
         */
        _trackManualResize() {
            if (!this.editor) return;
            const currentHeight = Math.round(this.editor.offsetHeight || 0);
            if (!currentHeight || !this._pointerDownHeight) return;

            if (Math.abs(currentHeight - this._pointerDownHeight) >= 2) {
                const minHeight = this._autoGrowMinHeight || 120;
                const maxHeight = this._autoGrowMaxHeight || 2000;
                const clamped = Math.max(minHeight, Math.min(maxHeight, currentHeight));

                this._manualHeightPx = clamped;
                this._manualResizeLocked = true;
                this._lastKnownEditorHeight = clamped;
                if (Math.abs(clamped - currentHeight) >= 2) {
                    this._isAutoSizing = true;
                    this.editor.style.height = `${clamped}px`;
                    requestAnimationFrame(() => { this._isAutoSizing = false; });
                }
                window.RTE_debugLog('resize', `Manual height locked at ${clamped}px`);
            }
        }

        /**
         * Resolve wrapper data-height value to px integer
         * @private
         * @param {string} rawValue
         * @param {number} fallback
         * @returns {number}
         */
        _resolveHeightValue(rawValue, fallback) {
            const parsed = parseInt(String(rawValue || '').trim(), 10);
            if (Number.isFinite(parsed) && parsed > 0) {
                return parsed;
            }
            return fallback;
        }

        /**
         * Apply disabled toolbar controls from wrapper data attribute.
         * @private
         */
        _applyDisabledButtonsConfig() {
            if (!this.wrapper || !this.toolbar) return;

            const raw = String(this.wrapper.dataset.disabledButtons || '').trim();
            if (!raw) return;

            const disabledKeys = new Set(
                raw.split(',')
                    .map(v => String(v || '').trim().toLowerCase())
                    .filter(Boolean)
            );
            if (disabledKeys.size === 0) return;

            const controls = this.toolbar.querySelectorAll('[data-command], [data-action], [data-view]');
            controls.forEach((el) => {
                const key = String(el.getAttribute('data-command') || el.getAttribute('data-action') || el.getAttribute('data-view') || '').trim().toLowerCase();
                if (!key || !disabledKeys.has(key)) return;
                el.setAttribute('disabled', 'disabled');
                el.setAttribute('aria-disabled', 'true');
            });
        }

        /**
         * Setup handlers from helper modules
         * @private
         */
        _setupHelperHandlers() {
            if (typeof this.setupPasteHandler === 'function') this.setupPasteHandler();
            if (typeof this.setupDragDropHandlers === 'function') this.setupDragDropHandlers();
            if (typeof this.setupFigureHandlers === 'function') this.setupFigureHandlers();
        }

        /**
         * Setup touch event handlers
         * @private
         */
        _setupTouchEvents() {
            this.editor.addEventListener('touchend', (e) => {
                window.RTE_debugLog('touch', 'Touch end - finalizing selection');

                if (this._finalizePending) {
                    window.RTE_debugLog('touch', 'Skipping: finalization already pending');
                    return;
                }

                this._finalizePending = true;

                requestAnimationFrame(() => {
                    setTimeout(() => {
                        try {
                            this.updateButtonStates();
                            this.syncHeadingSelect();
                            this.finalizeSelection();
                            window.RTE_debugLog('touch', 'Selection finalized');
                        } catch (err) {
                            console.warn('Touch finalization error:', err);
                        } finally {
                            this._finalizePending = false;
                        }
                    }, 10);
                });
            });
        }

        /**
         * Setup IME (Input Method Editor) event handlers
         * For Asian language input (Chinese, Japanese, Korean, etc.)
         * @private
         */
        _setupIMEEvents() {
            this.editor.addEventListener('compositionstart', () => {
                window.RTE_debugLog('ime', 'IME composition started');
                this._inIMEComposition = true;
                this._selectionFinal = false;
            });

            this.editor.addEventListener('compositionend', () => {
                window.RTE_debugLog('ime', 'IME composition ended');
                this._inIMEComposition = false;

                if (this._finalizePending) {
                    window.RTE_debugLog('ime', 'Skipping: finalization already pending');
                    return;
                }

                this._finalizePending = true;

                requestAnimationFrame(() => {
                    setTimeout(() => {
                        try {
                            this.updateButtonStates();
                            this.syncHeadingSelect();
                            this.finalizeSelection();
                            window.RTE_debugLog('ime', 'Selection finalized after IME');
                        } catch (err) {
                            console.warn('IME finalization error:', err);
                        } finally {
                            this._finalizePending = false;
                        }
                    }, 10);
                });
            });
        }

        /**
         * Dispatch editor ready event
         * @private
         */
        _dispatchReadyEvent() {
            try {
                const evt = new CustomEvent('rte:ready', {
                    detail: { editorId: this.editorId, editor: this }
                });
                document.dispatchEvent(evt);
            } catch (err) {
                // Fallback for older browsers
                const evt = document.createEvent('CustomEvent');
                evt.initCustomEvent('rte:ready', true, true, {
                    editorId: this.editorId,
                    editor: this
                });
                document.dispatchEvent(evt);
            }
        }

        // =========================================================================
        // EVENT SETUP
        // =========================================================================

        /**
         * Setup editor content event handlers
         */
        setupEditorEvents() {
            // make extra sure editor stays editable before we wire up events
            try {
                if (this.editor && this.editor.getAttribute('contenteditable') !== 'true') {
                    this.editor.setAttribute('contenteditable', 'true');
                }
            } catch (e) {
                // ignore
            }

            // Input event - save to history
            this.editor.addEventListener('input', () => {
                this.updateHiddenInput();
                this.updatePlaceholder();
                this.autoGrowEditorHeight();
                this.debouncedSaveToHistory();
            });

            // Selection change event (debounced)
            this._setupSelectionChangeHandler();

            // Focus and blur events
            this.editor.addEventListener('focus', () => this.updatePlaceholder());
            this.editor.addEventListener('blur', () => {
                this.updatePlaceholder();
                this.updateHiddenInput();
            });

            // Mouse events
            this._setupMouseEvents();

            // Keyboard events
            this._setupKeyboardEvents();

            // Source view sync
            if (this.sourceView) {
                this.sourceView.addEventListener('input', () => {
                    // Auto-sync is optional - we sync on view switch
                });
            }

            // Initial placeholder state
            this.updatePlaceholder();
            this.autoGrowEditorHeight({ force: true });

            // Initial toolbar sync
            this._initialToolbarSync();
        }

        /**
         * Setup debounced selection change handler
         * @private
         */
        _setupSelectionChangeHandler() {
            if (this._boundSelectionChangeHandler) {
                document.removeEventListener('selectionchange', this._boundSelectionChangeHandler);
            }

            this._boundSelectionChangeHandler = () => {
                if (!this.editor) return;

                const active = document.activeElement;
                if (active === this.editor || this.editor.contains(active)) {
                    // Clear previous timer
                    if (this._selectionChangeTimer) {
                        clearTimeout(this._selectionChangeTimer);
                    }

                    // Debounce: 16ms (1 frame at 60fps)
                    this._selectionChangeTimer = setTimeout(() => {
                        window.RTE_debugLog('selection', 'Selection changed (debounced)');
                        this.updateButtonStates();
                        this.syncHeadingSelect();
                        this._selectionChangeTimer = null;
                    }, 16);
                }
            };

            document.addEventListener('selectionchange', this._boundSelectionChangeHandler);
        }

        /**
         * Setup mouse event handlers
         * @private
         */
        _setupMouseEvents() {
            // Mouse down - clear finalization flag
            this.editor.addEventListener('mousedown', () => {
                this._pointerDownHeight = Math.round(this.editor.offsetHeight || 0);
                this._selectionFinal = false;
                this._updateDebugIndicator('none');
            });

            this.editor.addEventListener('pointerdown', () => {
                this._pointerDownHeight = Math.round(this.editor.offsetHeight || 0);
                window.addEventListener('pointerup', () => {
                    this._trackManualResize();
                }, { once: true, capture: true });
            });

            // Mouse up - finalize selection
            this.editor.addEventListener('mouseup', () => {
                this._trackManualResize();
                window.RTE_debugLog('mouse', 'Mouse up - finalizing selection');
                this.updateButtonStates();
                this.syncHeadingSelect();

                try {
                    this.finalizeSelection();
                } catch (err) {
                    console.warn('Mouse finalization error:', err);
                }
            });

            this.editor.addEventListener('pointerup', () => {
                this._trackManualResize();
            });

            // Click event
            this.editor.addEventListener('click', (e) => {
                // Toggle selected class for figures
                const fig = e.target.closest('figure');
                this.editor.querySelectorAll('figure.rte-figure-selected').forEach(f => {
                    f.classList.remove('rte-figure-selected');
                });
                if (fig) {
                    fig.classList.add('rte-figure-selected');
                }

                // Update button states
                setTimeout(() => this.updateButtonStates(), 10);
            });
        }

        /**
         * Setup keyboard event handlers
         * @private
         */
        _setupKeyboardEvents() {
            this.editor.addEventListener('keyup', (e) => {
                window.RTE_debugLog('keyboard', 'Key up:', e.key);

                // Skip if in IME composition
                if (this._inIMEComposition) {
                    window.RTE_debugLog('keyboard', 'Skipping: IME in progress');
                    return;
                }

                // Ignore pure modifier keys
                if (['Shift', 'Control', 'Alt', 'Meta'].includes(e.key)) {
                    return;
                }

                // Check if selection-completing key
                const selectionKeys = [
                    'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown',
                    'Home', 'End', 'PageUp', 'PageDown'
                ];
                const isSelectionKey = selectionKeys.includes(e.key) || e.shiftKey;

                // Early exit if no selection
                try {
                    const sel = window.getSelection();
                    if (!sel || sel.rangeCount === 0 || sel.isCollapsed) {
                        this._updateDebugIndicator('none');
                        return;
                    }
                } catch (err) {
                    console.warn('Selection check error:', err);
                    return;
                }

                // Only finalize for selection-completing keys
                if (!isSelectionKey) {
                    return;
                }

                try {
                    this.finalizeSelection();
                } catch (err) {
                    console.warn('Keyboard finalization error:', err);
                }
            });
        }

        /**
         * Initial toolbar synchronization
         * @private
         */
        _initialToolbarSync() {
            this.syncHeadingSelect();
            if (typeof this.syncFontSelect === 'function') this.syncFontSelect();
            if (typeof this.syncFontSizeSelect === 'function') this.syncFontSizeSelect();
            // Color sync skipped on init (requires finalized selection)
        }

        /**
         * Setup toolbar button event handlers
         */
        setupToolbarEvents() {
            const buttons = this.toolbar.querySelectorAll('button, select');
            const saveSelOnInteract = this._createSelectionSaver();

            buttons.forEach(button => {
                this._setupButtonEvents(button, saveSelOnInteract);
            });

            // Setup special controls
            this._setupHeadingSelect(saveSelOnInteract);
            this._setupFontSelect(saveSelOnInteract);
            this._setupFontSizeSelect(saveSelOnInteract);
            this._setupColorInputs(saveSelOnInteract);
        }

        /**
         * Create a selection saver function for toolbar interactions
         * @private
         * @returns {Function}
         */
        _createSelectionSaver() {
            return (el) => {
                const save = () => {
                    try {
                        this.saveSelection();
                    } catch (err) {
                        console.warn('Selection save error:', err);
                    }
                };

                // Mouse/pointer/touch events
                el.addEventListener('mousedown', save);
                el.addEventListener('pointerdown', save);
                el.addEventListener('touchstart', save, { passive: true });

                // Keyboard events
                el.addEventListener('keydown', (ev) => {
                    if (ev.key === ' ' || ev.key === 'Enter') {
                        save();
                    }
                });

                // Focus event (accessibility)
                el.addEventListener('focus', save);
            };
        }

        /**
         * Setup individual button events
         * @private
         */
        _setupButtonEvents(button, saveSelOnInteract) {
            // View toggle buttons
            if (button.classList.contains('rte-btn-view')) {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    const view = button.dataset.view;
                    if (view) this.switchView(view);
                });
                return;
            }

            // Format command buttons
            if (button.dataset.command && button.tagName === 'BUTTON') {
                saveSelOnInteract(button);
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    this._handleCommandButton(button);
                });
                return;
            }

            // Action buttons
            if (button.dataset.action) {
                saveSelOnInteract(button);
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    this._handleActionButton(button);
                });
            }
        }

        /**
         * Handle command button click
         * @private
         */
        _handleCommandButton(button) {
            const command = button.dataset.command;
            const value = button.dataset.value || null;

            window.RTE_debugLog('command', `Button clicked: ${command}`);

            this.restoreSelection();
            this.executeCommand(command, value);
            this.updateHiddenInput();
            this.saveToHistory();
            this.updateButtonStates();

            setTimeout(() => this._focusEditor(), 10);
        }

        /**
         * Handle action button click
         * @private
         */
        _handleActionButton(button) {
            const action = button.dataset.action;

            window.RTE_debugLog('action', `Button clicked: ${action}`);

            this.restoreSelection();
            const result = this.handleAction(action);

            const finalizeAction = (resolved) => {
                // Returning false means the action fully handled its own updates/history.
                if (resolved !== false) {
                    this.updateHiddenInput();
                    this.saveToHistory();
                }
                setTimeout(() => this._focusEditor(), 10);
            };

            if (result && typeof result.then === 'function') {
                result
                    .then((resolved) => finalizeAction(resolved))
                    .catch((err) => {
                        console.warn(`Action error (${action}):`, err);
                        setTimeout(() => this._focusEditor(), 10);
                    });
                return;
            }

            finalizeAction(result);
        }

        /**
         * Setup heading dropdown
         * @private
         */
        _setupHeadingSelect(saveSelOnInteract) {
            const headingSelect = this.toolbar.querySelector('.rte-heading-select');
            if (!headingSelect) return;

            saveSelOnInteract(headingSelect);
            headingSelect.addEventListener('change', (e) => {
                const tag = e.target.value || 'p';
                this.restoreSelection();

                try {
                    document.execCommand('formatBlock', false, `<${tag}>`);
                } catch (err) {
                    console.warn('formatBlock error:', err);
                }

                headingSelect.blur();
                setTimeout(() => this._focusEditor(), 10);
            });
        }

        /**
         * Setup font family select
         * @private
         */
        _setupFontSelect(saveSelOnInteract) {
            const fontSelect = this.toolbar.querySelector('.rte-font-select');
            if (!fontSelect) return;

            saveSelOnInteract(fontSelect);
            fontSelect.addEventListener('change', (e) => {
                const selectedFont = e.target.value;
                if (!selectedFont) {
                    fontSelect.blur();
                    setTimeout(() => this._focusEditor(), 0);
                    return;
                }

                const applyFallback = () => {
                    this.restoreSelection();
                    document.execCommand('fontName', false, selectedFont);
                    this.updateHiddenInput();
                    this.saveToHistory();
                    fontSelect.blur();
                    setTimeout(() => this._focusEditor(), 10);
                };

                const range = this._getToolbarActionRange();
                if (!range || range.collapsed) {
                    applyFallback();
                    return;
                }

                let markers = null;
                try {
                    markers = this._placeSelectionMarkers(range);
                    if (!markers) {
                        applyFallback();
                        return;
                    }

                    this._selectBetweenMarkers(markers);
                    document.execCommand('fontName', false, selectedFont);
                    this.updateHiddenInput();
                    this.saveToHistory();
                    fontSelect.blur();
                    this._focusAndReselect(markers, true);
                } catch (err) {
                    console.warn('fontName marker flow failed, using fallback:', err);
                    this._restoreAndCleanupMarkers(markers, true);
                    this._cleanupMarkerLeftovers();
                    applyFallback();
                }
            });
        }

        /**
         * Setup font size select
         * @private
         */
        _setupFontSizeSelect(saveSelOnInteract) {
            const sizeSelect = this.toolbar.querySelector('.rte-font-size-select');
            if (!sizeSelect) return;

            saveSelOnInteract(sizeSelect);

            // open datalist dropdown on focus when supported
            sizeSelect.addEventListener('focus', () => {
                if (typeof sizeSelect.showPicker === 'function') {
                    try { sizeSelect.showPicker(); } catch { } // some browsers may throw
                }
            });

            const applyFontSizeConversion = (numeric) => {
                // helper to convert using numeric value without suffix
                document.execCommand('fontSize', false, '7');

                const fonts = Array.from(this.editor.getElementsByTagName('font'));
                fonts.forEach(f => {
                    if (f.size == '7') {
                        const span = document.createElement('span');
                        span.style.fontSize = numeric + 'px';
                        span.innerHTML = f.innerHTML;
                        f.parentNode.replaceChild(span, f);
                    }
                });
            };

            const handleSize = (e) => {
                let raw = String(e.target.value || '').trim();
                // remove trailing px if user typed it themselves
                if (raw.toLowerCase().endsWith('px')) {
                    raw = raw.slice(0, -2).trim();
                }

                // if nothing remain, just allow editing and don't blur
                if (raw === '') {
                    return;
                }

                // only digits allowed
                const numeric = parseInt(raw, 10);
                if (Number.isNaN(numeric)) {
                    // invalid input, leave alone
                    return;
                }

                // update displayed value with px suffix every time
                sizeSelect.value = numeric + 'px';

                const applyFallback = () => {
                    this.restoreSelection();
                    applyFontSizeConversion(numeric);
                    this.updateHiddenInput();
                    this.saveToHistory();
                    sizeSelect.blur();
                    setTimeout(() => this._focusEditor(), 10);
                };

                const range = this._getToolbarActionRange();
                if (!range || range.collapsed) {
                    applyFallback();
                    return;
                }

                let markers = null;
                try {
                    markers = this._placeSelectionMarkers(range);
                    if (!markers) {
                        applyFallback();
                        return;
                    }

                    this._selectBetweenMarkers(markers);
                    applyFontSizeConversion(numeric);
                    this.updateHiddenInput();
                    this.saveToHistory();
                    sizeSelect.blur();
                    this._focusAndReselect(markers, true);
                } catch (err) {
                    console.warn('fontSize marker flow failed, using fallback:', err);
                    this._restoreAndCleanupMarkers(markers, true);
                    this._cleanupMarkerLeftovers();
                    applyFallback();
                }
            };

            sizeSelect.addEventListener('change', handleSize);
            sizeSelect.addEventListener('input', handleSize);

            // blur the size control whenever the editor gets focus so typing isn't captured
            this.editor.addEventListener('focus', () => {
                if (document.activeElement === sizeSelect) {
                    sizeSelect.blur();
                }
            });
        }

        /**
         * Get the best available range for toolbar actions.
         * @private
         * @returns {Range|null}
         */
        _getToolbarActionRange() {
            const isNodeInEditor = (node) => {
                if (!node || !this.editor) return false;
                return node === this.editor || this.editor.contains(node);
            };

            try {
                const sel = window.getSelection();
                if (sel && sel.rangeCount > 0) {
                    const range = sel.getRangeAt(0);
                    if (isNodeInEditor(range.startContainer) && isNodeInEditor(range.endContainer)) {
                        return range.cloneRange();
                    }
                }
            } catch (e) {
                // Silent fail
            }

            if (this._savedRange) {
                try {
                    const range = this._savedRange.cloneRange();
                    if (isNodeInEditor(range.startContainer) && isNodeInEditor(range.endContainer)) {
                        return range;
                    }
                } catch (e) {
                    // Silent fail
                }
            }

            return null;
        }

        /**
         * Place selection boundary markers.
         * @private
         * @param {Range} range
         * @returns {{start: HTMLElement, end: HTMLElement}|null}
         */
        _placeSelectionMarkers(range) {
            if (!range) return null;

            try {
                const markerStyle = 'display:inline-block;width:0;height:0;overflow:hidden;line-height:0;';
                const start = document.createElement('span');
                start.setAttribute('data-rte-marker', 'start');
                start.setAttribute('contenteditable', 'false');
                start.setAttribute('style', markerStyle);

                const end = document.createElement('span');
                end.setAttribute('data-rte-marker', 'end');
                end.setAttribute('contenteditable', 'false');
                end.setAttribute('style', markerStyle);

                const endRange = range.cloneRange();
                endRange.collapse(false);
                endRange.insertNode(end);

                const startRange = range.cloneRange();
                startRange.collapse(true);
                startRange.insertNode(start);

                return { start, end };
            } catch (e) {
                return null;
            }
        }

        /**
         * Select content between start/end markers.
         * @private
         * @param {{start: HTMLElement, end: HTMLElement}|null} markers
         * @returns {boolean}
         */
        _selectBetweenMarkers(markers) {
            if (!markers?.start || !markers?.end) return false;

            try {
                const range = document.createRange();
                range.setStartAfter(markers.start);
                range.setEndBefore(markers.end);

                const sel = window.getSelection();
                if (!sel) return false;
                sel.removeAllRanges();
                sel.addRange(range);
                this._savedRange = range.cloneRange();
                return true;
            } catch (e) {
                return false;
            }
        }

        /**
         * Restore selection from markers and remove markers safely.
         * @private
         * @param {{start: HTMLElement, end: HTMLElement}|null} markers
         * @param {boolean} keepSelected
         * @returns {boolean}
         */
        _restoreAndCleanupMarkers(markers, keepSelected = true) {
            let restored = false;

            try {
                if (keepSelected) {
                    restored = this._selectBetweenMarkers(markers);
                } else if (markers?.end && markers.end.parentNode) {
                    const range = document.createRange();
                    range.setStartAfter(markers.end);
                    range.collapse(true);
                    const sel = window.getSelection();
                    if (sel) {
                        sel.removeAllRanges();
                        sel.addRange(range);
                        this._savedRange = range.cloneRange();
                        restored = true;
                    }
                }
            } catch (e) {
                restored = false;
            }

            try {
                if (markers?.start?.parentNode) markers.start.parentNode.removeChild(markers.start);
                if (markers?.end?.parentNode) markers.end.parentNode.removeChild(markers.end);
            } catch (e) {
                // Silent fail
            }

            try {
                const sel = window.getSelection();
                if (sel && sel.rangeCount > 0) {
                    const range = sel.getRangeAt(0);
                    if (this.editor.contains(range.startContainer) && this.editor.contains(range.endContainer)) {
                        this._savedRange = range.cloneRange();
                    }
                }
            } catch (e) {
                // Silent fail
            }

            return restored;
        }

        /**
         * Focus editor first, then restore selection from markers.
         * @private
         * @param {{start: HTMLElement, end: HTMLElement}|null} markers
         * @param {boolean} keepSelected
         */
        _focusAndReselect(markers, keepSelected = true) {
            this._focusEditor();

            const restored = this._restoreAndCleanupMarkers(markers, keepSelected);
            if (!restored) {
                setTimeout(() => {
                    this._restoreAndCleanupMarkers(markers, keepSelected);
                }, 0);
            }
        }

        /**
         * Remove any leftover marker spans inside current editor.
         * @private
         */
        _cleanupMarkerLeftovers() {
            try {
                this.editor.querySelectorAll('[data-rte-marker]').forEach((node) => node.remove());
            } catch (e) {
                // Silent fail
            }
        }

        /**
         * Setup color input controls
         * @private
         */
        _setupColorInputs(saveSelOnInteract) {
            this._setupColorInput('.rte-text-color-input', false, saveSelOnInteract);
            this._setupColorInput('.rte-bg-color-input', true, saveSelOnInteract);
        }

        /**
         * Setup individual color input
         * @private
         */
        _setupColorInput(selector, isBg, saveSelOnInteract) {
            const colorInput = this.toolbar.querySelector(selector);
            if (!colorInput) return;

            // Setup label click handler
            const label = colorInput.closest('.rte-color-wrapper')?.querySelector('.rte-color-label');
            if (label) {
                saveSelOnInteract(label);
                this._setupColorLabel(label, colorInput);
            }

            // Save selection on mousedown (capturing phase)
            colorInput.addEventListener('mousedown', () => {
                window.RTE_debugLog('color', `${isBg ? 'BG' : 'Text'} color mousedown`);
                this.saveSelection();
            }, true);

            // Prevent selection clear on focus
            colorInput.addEventListener('focus', (e) => {
                e.preventDefault();
            }, true);

            // Apply color on input/change
            colorInput.addEventListener('input', (e) => {
                this._applyColorFromInput(e.target.value, isBg);
            });

            colorInput.addEventListener('change', (e) => {
                this._applyColorFromInput(e.target.value, isBg);
            });
        }

        /**
         * Setup color label click handler
         * @private
         */
        _setupColorLabel(label, input) {
            if (!label || !input) return;
            if (label.getAttribute('data-rte-color-label-bound') === '1') return;
            label.setAttribute('data-rte-color-label-bound', '1');

            label.addEventListener('click', (e) => {
                e.preventDefault();
                input.click();
            });

            label.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    input.click();
                }
            });
        }

        /**
         * Apply color from input
         * @private
         */
        _applyColorFromInput(color, isBg) {
            if (!color) return;

            window.RTE_debugLog('color', `Applying ${isBg ? 'BG' : 'text'} color:`, color);
            window.RTE_debugLog('color', 'Color picker changed');

            if (!this.restoreSelection()) {
                console.warn('Cannot restore selection for color apply');
                return;
            }
            window.RTE_debugLog('color', 'Selection restored for color apply');

            // Apply the color
            this.applyColor(color, isBg);
            window.RTE_debugLog('color', 'applyColor executed');

            // Finalize selection to trigger color sync
            this.finalizeSelection();
            window.RTE_debugLog('color', 'finalizeSelection called after color apply');
        }

        /**
         * Setup color picker label handlers
         */
        setupColorPickerLabels() {
            const colorLabels = this.toolbar.querySelectorAll('.rte-color-label');
            const colorInputs = this.toolbar.querySelectorAll('.rte-color-input');

            colorLabels.forEach(label => this._setupColorPickerLabel(label));
            colorInputs.forEach(input => this._setupColorIndicator(input));
        }

        /**
         * Setup individual color picker label
         * @private
         */
        _setupColorPickerLabel(label) {
            if (!label) return;
            if (label.getAttribute('data-rte-color-label-bound') === '1') return;
            label.setAttribute('data-rte-color-label-bound', '1');

            const getInput = () => {
                const inputId = label.getAttribute('for');
                return inputId ? document.getElementById(inputId) : label.previousElementSibling;
            };

            label.addEventListener('click', (e) => {
                e.preventDefault();
                const input = getInput();
                if (input?.type === 'color') input.click();
            });

            label.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    const input = getInput();
                    if (input?.type === 'color') input.click();
                }
            });
        }

        /**
         * Setup color indicator updates
         * @private
         */
        _setupColorIndicator(input) {
            const updateIndicator = (color) => {
                const type = input.classList.contains('rte-text-color-input') ? 'text' : 'bg';
                const indicator = this.toolbar.querySelector(
                    `.rte-color-indicator[data-color-type="${type}"]`
                ) || input.closest('.rte-color-wrapper')?.querySelector('.rte-color-indicator');

                if (indicator) {
                    indicator.style.backgroundColor = color;
                }
                // Also update label/icon inline styles for immediate visual feedback
                try {
                    const wrapper = input.closest('.rte-color-wrapper');
                    if (wrapper) {
                        const label = wrapper.querySelector('.rte-color-label');
                        const icon = label ? label.querySelector('.rte-icon') : null;
                        if (type === 'text') {
                            // set icon color for text color picker and label text color
                            if (icon) icon.style.color = color || '';
                            if (label) label.style.color = color || '';
                            if (label) label.style.backgroundColor = 'transparent';
                        } else {
                            // set label background for background-color picker
                            if (label) label.style.backgroundColor = color || 'transparent';
                            if (icon) icon.style.color = '';
                            if (label) label.style.color = '';
                        }
                    }
                } catch (e) {
                    // ignore
                }
            };

            // Initialize indicator to current input value so label shows initial color
            try {
                updateIndicator(input.value);
            } catch (e) {
                // ignore
            }

            input.addEventListener('change', (e) => updateIndicator(e.target.value));
            input.addEventListener('input', (e) => updateIndicator(e.target.value));
        }

        /**
         * Setup keyboard shortcuts
         */
        setupKeyboardShortcuts() {
            if (this._isDelegated('setupKeyboardShortcuts')) {
                return this._delegate('setupKeyboardShortcuts');
            }

            // Fallback keyboard shortcuts
            this.editor.addEventListener('keydown', (e) => {
                const isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
                const cmdKey = isMac ? e.metaKey : e.ctrlKey;

                if (!cmdKey) return;

                const shortcuts = {
                    'b': () => this.executeCommand('bold'),
                    'i': () => this.executeCommand('italic'),
                    'u': () => this.executeCommand('underline'),
                    'z': () => !e.shiftKey && this.undo(),
                    'y': () => this.redo()
                };

                const handler = shortcuts[e.key.toLowerCase()];
                if (handler) {
                    e.preventDefault();
                    handler();
                }

                // Cmd+Shift+Z for redo (Mac style)
                if (e.key.toLowerCase() === 'z' && e.shiftKey) {
                    e.preventDefault();
                    this.redo();
                }

                // view toggles (Ctrl/Cmd+Shift+[C,S,H])
                if (e.shiftKey && cmdKey) {
                    const viewMap = {
                        'c': 'compose',
                        's': 'source',
                        'h': 'history'
                    };
                    const viewKey = e.key.toLowerCase();
                    if (viewMap[viewKey]) {
                        e.preventDefault();
                        this.switchView(viewMap[viewKey]);
                    }
                }
            });
        }

        // =========================================================================
        // CORE EDITOR COMMANDS
        // =========================================================================

        /**
         * Execute document command
         * @param {string} command - Command name
         * @param {*} value - Command value
         */
        executeCommand(command, value = null) {
            try {
                document.execCommand(command, false, value);
                this.updateHiddenInput();
                this.saveToHistory();
                this.updateButtonStates();

                // Trigger toolbar sync after command for consistency
                if (typeof this.updateToolbarStates === 'function') {
                    setTimeout(() => this.updateToolbarStates(), 0);
                }
            } catch (err) {
                console.warn(`execCommand error (${command}):`, err);
            }
        }

        /**
         * Handle special actions
         * @param {string} action - Action name
         */
        handleAction(action) {
            const actions = {
                'insertLink': () => this.showLinkModal(),
                'insertImage': () => this.showImageModal(),
                'insertVideo': () => this.showVideoModal(),
                'insertSpecialChar': () => this.showSpecialCharModal(),
                'clearFormatting': () => this.clearFormatting(),
                'copyClipboard': () => {
                    if (typeof this.copyToClipboard === 'function') {
                        return this.copyToClipboard();
                    }
                    try {
                        document.execCommand('copy');
                    } catch (err) {
                        console.warn('execCommand copy failed:', err);
                    }
                    return false;
                },
                'pasteClipboard': () => {
                    if (typeof this.pasteFromClipboard === 'function') {
                        return this.pasteFromClipboard();
                    }
                    try {
                        document.execCommand('paste');
                    } catch (err) {
                        console.warn('execCommand paste failed:', err);
                    }
                    return false;
                },
                'toggleMore': () => this.toggleMoreMenu(),
                'setLTR': () => this.setLTR(),
                'setRTL': () => this.setRTL(),
                'aiEnhance': async () => {
                    if (typeof window.adminAssistantRewrite === 'function') {
                        const original = this.getContent();
                        appendLoadingToast('Enhancing with AI...');
                        const improved = await window.adminAssistantRewrite(original);
                        this.setContent(improved);
                        removeLoadingToast();
                    } else {
                        console.warn('AI assistant not available');
                    }
                }
            };

            const handler = actions[action];
            if (handler) {
                return handler();
            } else {
                console.warn(`Unknown action: ${action}`);
                return false;
            }
        }

        // =========================================================================
        // SELECTION MANAGEMENT
        // =========================================================================

        /**
         * Save current selection
         * @param {string} purpose - 'general' or 'color'
         */
        saveSelection(purpose = 'general') {
            try {
                const sel = window.getSelection();
                if (!sel || sel.rangeCount === 0) {
                    window.RTE_debugLog('selection', 'No selection to save');
                    return;
                }

                const range = sel.getRangeAt(0).cloneRange();

                if (purpose === 'color') {
                    this._savedColorRange = range;
                    window.RTE_debugLog('selection', 'Color range saved');
                } else {
                    this._savedRange = range;
                    window.RTE_debugLog('selection', 'General range saved');
                    this._updateDebugIndicator('saved');
                }
            } catch (e) {
                console.warn('saveSelection error:', e);
            }
        }

        /**
         * Restore previously saved selection
         * @param {string} purpose - 'general' or 'color'
         * @returns {boolean} Success status
         */
        restoreSelection(purpose = 'general') {
            const range = purpose === 'color' ? this._savedColorRange : this._savedRange;

            if (!range) {
                window.RTE_debugLog('selection', `No ${purpose} range to restore`);
                return false;
            }

            try {
                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(range);

                this._focusEditor();

                window.RTE_debugLog('selection', `${purpose} range restored`);

                if (purpose !== 'color') {
                    this._updateDebugIndicator('restored');
                }

                return true;
            } catch (e) {
                console.warn(`restoreSelection error (${purpose}):`, e);
                return false;
            }
        }

        /**
         * Select all editor content
         */
        selectAll() {
            try {
                const range = document.createRange();
                range.selectNodeContents(this.editor);

                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(range);

                this._focusEditor();
            } catch (e) {
                console.warn('selectAll error:', e);
            }
        }

        /**
         * Finalize current selection
         * Validates and marks selection as complete
         */
        finalizeSelection() {
            // Skip if in IME composition
            if (this._inIMEComposition) {
                window.RTE_debugLog('selection', 'Skipping: IME in progress');
                return;
            }

            // Prevent duplicate concurrent calls
            if (this._finalizationInProgress) {
                window.RTE_debugLog('selection', 'Skipping: Already finalizing');
                return;
            }

            this._finalizationInProgress = true;

            try {
                const sel = window.getSelection();

                // Validate selection exists
                if (!sel || sel.rangeCount === 0) {
                    this._selectionFinal = false;
                    this._updateDebugIndicator('none');
                    return;
                }

                // Capture current range (collapsed and non-collapsed are both valid)
                const range = sel.getRangeAt(0);

                // Validate selection is within editor
                const startInEditor = this.editor.contains(range.startContainer) ||
                    range.startContainer === this.editor;
                const endInEditor = this.editor.contains(range.endContainer) ||
                    range.endContainer === this.editor;

                if (!startInEditor || !endInEditor) {
                    window.RTE_debugLog('selection', 'Selection spans outside editor');
                    this._selectionFinal = false;
                    this._updateDebugIndicator('none');
                    return;
                }

                // Selection is valid - mark as finalized
                this._selectionFinal = true;
                this._updateDebugIndicator('complete');

                window.RTE_debugLog('selection', 'Selection finalized');

                // Update all toolbar states (heading, font, color, buttons, etc.)
                if (typeof this.updateToolbarStates === 'function') {
                    this.updateToolbarStates();
                } else {
                    // Fallback if updateToolbarStates not available
                    this.updateButtonStates();
                    this.syncHeadingSelect();
                    if (typeof this.syncFontSelect === 'function') this.syncFontSelect();
                    if (typeof this.syncFontSizeSelect === 'function') this.syncFontSizeSelect();
                    if (typeof this.syncColorInputs === 'function') this.syncColorInputs();
                }

            } catch (err) {
                console.warn('finalizeSelection error:', err);
            } finally {
                this._finalizationInProgress = false;
            }
        }

        // =========================================================================
        // UI STATE MANAGEMENT
        // =========================================================================

        /**
         * Update toolbar button states
         */
        updateButtonStates() {
            if (this._isDelegated('updateButtonStates')) {
                return this._delegate('updateButtonStates');
            }

            // Fallback implementation
            const buttons = this.toolbar.querySelectorAll('button[data-command]');

            buttons.forEach(button => {
                const command = button.dataset.command;

                try {
                    const state = document.queryCommandState(command);
                    button.classList.toggle('rte-btn-active', state);
                } catch (err) {
                    button.classList.remove('rte-btn-active');
                }
            });
        }

        /**
         * Sync heading select with current block
         */
        syncHeadingSelect() {
            if (this._isDelegated('syncHeadingSelect')) {
                return this._delegate('syncHeadingSelect');
            }

            // Fallback implementation
            const select = this.toolbar?.querySelector('.rte-heading-select');
            if (select) select.value = 'p';
        }

        /**
         * Sync font family select
         */
        syncFontSelect() {
            if (this._isDelegated('syncFontSelect')) {
                return this._delegate('syncFontSelect');
            }
        }

        /**
         * Sync font size select
         */
        syncFontSizeSelect() {
            if (this._isDelegated('syncFontSizeSelect')) {
                return this._delegate('syncFontSizeSelect');
            }
        }

        /**
         * Sync color inputs
         */
        syncColorInputs() {
            if (this._isDelegated('syncColorInputs')) {
                return this._delegate('syncColorInputs');
            }
        }

        // =========================================================================
        // VIEW MANAGEMENT
        // =========================================================================

        /**
         * Switch between editor views
         * @param {string} view - View name ('compose', 'source', 'history')
         */
        switchView(view) {
            if (this._isDelegated('switchView')) {
                return this._delegate('switchView', view);
            }
        }

        /**
         * Sync source view to editor
         */
        syncSourceToEditor() {
            if (this._isDelegated('syncSourceToEditor')) {
                return this._delegate('syncSourceToEditor');
            }
        }

        // =========================================================================
        // HISTORY MANAGEMENT
        // =========================================================================

        /**
         * Save current state to history
         */
        saveToHistory() {
            if (this._isDelegated('saveToHistory')) {
                return this._delegate('saveToHistory');
            }
        }

        /**
         * Debounced save to history
         */
        debouncedSaveToHistory() {
            if (this._isDelegated('debouncedSaveToHistory')) {
                return this._delegate('debouncedSaveToHistory');
            }

            // Fallback
            if (this.historyTimeout) clearTimeout(this.historyTimeout);
            this.historyTimeout = setTimeout(() => this.saveToHistory(), this.historyDelay);
        }

        /**
         * Undo last action
         */
        undo() {
            if (this._isDelegated('undo')) {
                return this._delegate('undo');
            }
        }

        /**
         * Redo last action
         */
        redo() {
            if (this._isDelegated('redo')) {
                return this._delegate('redo');
            }
        }

        /**
         * Restore from history
         * @param {number} index - History index
         */
        restoreFromHistory(index) {
            if (this._isDelegated('restoreFromHistory')) {
                return this._delegate('restoreFromHistory', index);
            }
        }

        /**
         * Update history view
         */
        updateHistoryView() {
            if (this._isDelegated('updateHistoryView')) {
                return this._delegate('updateHistoryView');
            }
        }

        /**
         * Check if can undo
         * @returns {boolean}
         */
        canUndo() {
            if (this._isDelegated('canUndo')) {
                return this._delegate('canUndo');
            }
            return false;
        }

        /**
         * Check if can redo
         * @returns {boolean}
         */
        canRedo() {
            if (this._isDelegated('canRedo')) {
                return this._delegate('canRedo');
            }
            return false;
        }

        // =========================================================================
        // FORMATTING
        // =========================================================================

        /**
         * Clear all formatting from selection
         */
        clearFormatting() {
            if (this._isDelegated('clearFormatting')) {
                return this._delegate('clearFormatting');
            }
        }

        /**
         * Set text direction to LTR
         */
        setLTR() {
            if (this._isDelegated('setLTR')) {
                return this._delegate('setLTR');
            }
        }

        /**
         * Set text direction to RTL
         */
        setRTL() {
            if (this._isDelegated('setRTL')) {
                return this._delegate('setRTL');
            }
        }

        // =========================================================================
        // COLOR MANAGEMENT
        // =========================================================================

        /**
         * Apply color to selection
         * @param {string} color - Color value
         * @param {boolean} isBg - Is background color
         */
        applyColor(color, isBg = false) {
            if (this._isDelegated('applyColor')) {
                return this._delegate('applyColor', color, isBg);
            }
        }

        /**
         * Normalize font tags to spans
         */
        normalizeFontTags() {
            try {
                const fonts = Array.from(this.editor.getElementsByTagName('font'));

                fonts.forEach(f => {
                    const span = document.createElement('span');

                    const color = f.getAttribute('color') || f.getAttribute('data-color');
                    if (color) span.style.color = color;

                    const style = f.getAttribute('style');
                    if (style) span.setAttribute('style', (span.getAttribute('style') || '') + ';' + style);

                    span.innerHTML = f.innerHTML;

                    if (f.parentNode) {
                        f.parentNode.replaceChild(span, f);
                    }
                });
            } catch (e) {
                console.warn('normalizeFontTags error:', e);
            }
        }

        // =========================================================================
        // MODALS
        // =========================================================================

        /**
         * Setup modals
         */
        setupModals() {
            if (this._isDelegated('setupModals')) {
                return this._delegate('setupModals');
            }
        }

        /**
         * Show link modal
         */
        showLinkModal() {
            if (this._isDelegated('showLinkModal')) {
                return this._delegate('showLinkModal');
            }
        }

        /**
         * Show image modal
         */
        showImageModal() {
            if (this._isDelegated('showImageModal')) {
                return this._delegate('showImageModal');
            }
        }

        /**
         * Show video modal
         */
        showVideoModal() {
            if (this._isDelegated('showVideoModal')) {
                return this._delegate('showVideoModal');
            }
        }

        /**
         * Show special character modal
         */
        showSpecialCharModal() {
            if (this._isDelegated('showSpecialCharModal')) {
                return this._delegate('showSpecialCharModal');
            }
        }

        /**
         * Close modal
         * @param {string} modalType - Modal type
         */
        closeModal(modalType) {
            if (this._isDelegated('closeModal')) {
                return this._delegate('closeModal', modalType);
            }
        }

        // =========================================================================
        // UI UTILITIES
        // =========================================================================

        /**
         * Toggle more menu
         */
        toggleMoreMenu() {
            if (this._isDelegated('toggleMoreMenu')) {
                return this._delegate('toggleMoreMenu');
            }
        }

        /**
         * Update placeholder visibility
         */
        updatePlaceholder() {
            if (this._isDelegated('updatePlaceholder')) {
                return this._delegate('updatePlaceholder');
            }
        }

        /**
         * Update hidden input value
         */
        updateHiddenInput() {
            if (this._isDelegated('updateHiddenInput')) {
                return this._delegate('updateHiddenInput');
            }

            // Fallback
            this.hiddenInput.value = this.editor.innerHTML;
        }

        /**
         * Escape HTML special characters
         * @param {string} text - Text to escape
         * @returns {string}
         */
        escapeHtml(text) {
            if (this._isDelegated('escapeHtml')) {
                return this._delegate('escapeHtml', text);
            }

            if (window.RTE_utils && typeof window.RTE_utils.escapeHtml === 'function') {
                return window.RTE_utils.escapeHtml(text);
            }

            // Safe fallback without map duplication.
            try {
                const temp = document.createElement('div');
                temp.textContent = String(text == null ? '' : text);
                return temp.innerHTML;
            } catch (e) {
                return String(text == null ? '' : text);
            }
        }

        // =========================================================================
        // SANITIZATION
        // =========================================================================

        /**
         * Sanitize HTML
         * @param {string} html - HTML to sanitize
         * @returns {string}
         */
        sanitizeHTML(html) {
            if (this._isDelegated('sanitizeHTML')) {
                return this._delegate('sanitizeHTML', html);
            }

            // Fail-closed fallback sanitizer (used only when helper not delegated).
            const raw = String(html || '');
            if (!raw) return '';

            if (typeof window !== 'undefined' && window.DOMPurify) {
                try {
                    return window.DOMPurify.sanitize(raw);
                } catch (e) {
                    console.warn('Core sanitize fallback DOMPurify failed:', e);
                }
            }

            try {
                const temp = document.createElement('div');
                temp.innerHTML = raw;

                // Remove obviously dangerous elements.
                temp.querySelectorAll('script,style,iframe,object,embed,link,meta,base').forEach((node) => {
                    if (node.parentNode) node.parentNode.removeChild(node);
                });

                temp.querySelectorAll('*').forEach((el) => {
                    Array.from(el.attributes).forEach((attr) => {
                        const name = String(attr.name || '').toLowerCase();
                        const value = String(attr.value || '').trim();

                        if (name.startsWith('on')) {
                            el.removeAttribute(attr.name);
                            return;
                        }
                        if (name === 'href' || name === 'src') {
                            const lower = value.toLowerCase();
                            const safeDataImage = /^data:image\/(?:png|jpe?g|gif|webp|bmp|avif);base64,[a-z0-9+/=\s]+$/i.test(value);
                            const isSafe = lower.startsWith('http://')
                                || lower.startsWith('https://')
                                || lower.startsWith('/')
                                || lower.startsWith('./')
                                || lower.startsWith('../')
                                || lower.startsWith('#')
                                || lower.startsWith('mailto:')
                                || lower.startsWith('tel:')
                                || safeDataImage;
                            if (!isSafe || lower.startsWith('javascript:') || lower.startsWith('vbscript:')) {
                                el.removeAttribute(attr.name);
                            }
                        }
                    });
                });

                return temp.innerHTML;
            } catch (e) {
                console.warn('Core sanitize fallback failed:', e);
                return '';
            }
        }

        // =========================================================================
        // PUBLIC API
        // =========================================================================

        /**
         * Get editor content
         * @returns {string}
         */
        getContent() {
            return this.editor.innerHTML;
        }

        /**
         * Set editor content
         * @param {string} html - HTML content
         */
        setContent(html) {
            const sanitized = this.sanitizeHTML(html);
            this.editor.innerHTML = sanitized;
            this.updateHiddenInput();
            this.autoGrowEditorHeight({ force: true });
            this.saveToHistory();
            this.updatePlaceholder();
        }

        /**
         * Clear editor content
         */
        clear() {
            this.editor.innerHTML = '';
            this.updateHiddenInput();
            this._manualHeightPx = 0;
            this.autoGrowEditorHeight({ force: true });
            this.saveToHistory();
            this.updatePlaceholder();
        }

        /**
         * Focus the editor
         * @returns {boolean}
         */
        focus() {
            return this._focusEditor();
        }

        /**
         * Blur the editor
         * @returns {boolean}
         */
        blur() {
            try {
                if (this.editor?.blur) {
                    this.editor.blur();
                    return true;
                }
            } catch (e) {
                console.warn('Blur error:', e);
            }
            return false;
        }

        /**
         * Get selection information
         * @returns {Object|null}
         */
        getSelectionInfo() {
            try {
                const sel = window.getSelection();

                if (!sel || sel.rangeCount === 0) {
                    return {
                        hasSelection: false,
                        selectedText: '',
                        startOffset: 0,
                        endOffset: 0
                    };
                }

                const range = sel.getRangeAt(0);

                return {
                    hasSelection: sel.toString().length > 0,
                    selectedText: sel.toString(),
                    startOffset: range.startOffset,
                    endOffset: range.endOffset,
                    commonAncestorContainer: range.commonAncestorContainer
                };
            } catch (e) {
                console.warn('getSelectionInfo error:', e);
                return null;
            }
        }

        /**
         * Show selection debug indicator
         * @param {boolean} enabled - Enable debug mode
         */
        showSelectionDebug(enabled) {
            try {
                this._selectionDebug = !!enabled;
                const dbg = this.toolbar?.querySelector('.rte-selection-debug');
                const dbgSection = this.toolbar?.querySelector('.rte-toolbar-section.rte-debug');

                if (dbg) {
                    dbg.style.display = this._selectionDebug ? 'inline-block' : 'none';
                }
                if (dbgSection) {
                    dbgSection.style.display = this._selectionDebug ? 'flex' : 'none';
                }

                window.RTE_debugLog('selection', `Selection debug ${enabled ? 'enabled' : 'disabled'}`);
            } catch (e) {
                console.warn('showSelectionDebug error:', e);
            }
        }

        /**
         * Destroy editor instance
         */
        destroy() {
            try {
                if (this._destroyed) {
                    return true;
                }

                window.RTE_debugLog('lifecycle', `Destroying editor: ${this.editorId}`);

                if (this._boundSelectionChangeHandler) {
                    document.removeEventListener('selectionchange', this._boundSelectionChangeHandler);
                    this._boundSelectionChangeHandler = null;
                }

                // Stop observer callbacks before DOM nodes are replaced.
                if (this._contentEditableObserver && typeof this._contentEditableObserver.disconnect === 'function') {
                    try { this._contentEditableObserver.disconnect(); } catch (e) { }
                    this._contentEditableObserver = null;
                }
                if (this._resizeObserver && typeof this._resizeObserver.disconnect === 'function') {
                    try { this._resizeObserver.disconnect(); } catch (e) { }
                    this._resizeObserver = null;
                }

                if (this._cmInstance) {
                    try { this._cmInstance.toTextArea(); } catch (e) { }
                    this._cmInstance = null;
                }

                // Remove DOM-bound listeners by replacing nodes with clean clones.
                if (this.editor && this.editor.parentNode) {
                    const clone = this.editor.cloneNode(true);
                    this.editor.parentNode.replaceChild(clone, this.editor);
                    this.editor = clone;
                }
                if (this.toolbar && this.toolbar.parentNode) {
                    const clone = this.toolbar.cloneNode(true);
                    this.toolbar.parentNode.replaceChild(clone, this.toolbar);
                    this.toolbar = clone;
                }
                if (this.sourceView && this.sourceView.parentNode) {
                    const clone = this.sourceView.cloneNode(true);
                    this.sourceView.parentNode.replaceChild(clone, this.sourceView);
                    this.sourceView = clone;
                }
                if (this.historyView && this.historyView.parentNode) {
                    const clone = this.historyView.cloneNode(true);
                    this.historyView.parentNode.replaceChild(clone, this.historyView);
                    this.historyView = clone;
                }
                ['link', 'image', 'video', 'special-char'].forEach((name) => {
                    const modal = document.getElementById(`${this.editorId}-${name}-modal`);
                    if (modal && modal.parentNode) {
                        const clone = modal.cloneNode(true);
                        modal.parentNode.replaceChild(clone, modal);
                    }
                });

                // Clear all timers
                this._clearAllTimers();

                // Clear all references
                this._clearAllReferences();

                // Reset all flags
                this._resetAllFlags();

                // Mark as destroyed
                this._destroyed = true;

                window.RTE_debugLog('lifecycle', `Editor destroyed: ${this.editorId}`);
                return true;
            } catch (err) {
                console.error('destroy() error:', err);
                return false;
            }
        }

        /**
         * Production readiness verification
         * @returns {boolean} - True if all critical components are ready
         */
        verifyProductionReady() {
            const checks = {
                editor: !!this.editor,
                toolbar: !!this.toolbar,
                hiddenInput: !!this.hiddenInput,
                methods: {
                    updateToolbarStates: typeof this.updateToolbarStates === 'function',
                    finalizeSelection: typeof this.finalizeSelection === 'function',
                    applyColor: typeof this.applyColor === 'function',
                    executeCommand: typeof this.executeCommand === 'function'
                }
            };

            return checks.editor && checks.toolbar && checks.hiddenInput &&
                Object.values(checks.methods).every(v => v);
        }

        // =========================================================================
        // PRIVATE HELPER METHODS
        // =========================================================================

        /**
         * Focus the editor
         * @private
         * @returns {boolean}
         */
        _focusEditor() {
            try {
                if (this.editor?.focus) {
                    this.editor.focus();
                    return true;
                }
            } catch (e) {
                console.warn('Focus error:', e);
            }
            return false;
        }

        /**
         * Update debug indicator
         * @private
         */
        _updateDebugIndicator(state) {
            try {
                const dbg = this.toolbar?.querySelector('.rte-selection-debug');
                if (dbg) {
                    const labels = {
                        'none': 'None',
                        'saved': 'Saved',
                        'restored': 'Restored',
                        'complete': 'Complete'
                    };
                    dbg.textContent = labels[state] || state;
                    dbg.setAttribute('data-state', state);
                }
            } catch (e) {
                // Silent fail
            }
        }

        /**
         * Check if method should be delegated
         * @private
         */
        _isDelegated(methodName) {
            return typeof RichTextEditor !== 'undefined' &&
                RichTextEditor.prototype &&
                RichTextEditor.prototype[methodName] &&
                RichTextEditor.prototype[methodName] !== this[methodName];
        }

        /**
         * Delegate method call to prototype
         * @private
         */
        _delegate(methodName, ...args) {
            return RichTextEditor.prototype[methodName].call(this, ...args);
        }

        /**
         * Clear all timers
         * @private
         */
        _clearAllTimers() {
            if (this.historyTimeout) clearTimeout(this.historyTimeout);
            if (this.selectionSyncTimeout) clearTimeout(this.selectionSyncTimeout);
            if (this._selectionChangeTimer) clearTimeout(this._selectionChangeTimer);

            this.historyTimeout = null;
            this.selectionSyncTimeout = null;
            this._selectionChangeTimer = null;
        }

        /**
         * Clear all references
         * @private
         */
        _clearAllReferences() {
            this._savedRange = null;
            this._savedColorRange = null;
            this._pendingImageData = null;
            this._pendingImageFile = null;
            this._cmInstance = null;
            this._contentEditableObserver = null;
            this._resizeObserver = null;
            if (this._pendingImagePreviewUrl) {
                try { URL.revokeObjectURL(this._pendingImagePreviewUrl); } catch (e) { /* ignore */ }
            }
            this._pendingImagePreviewUrl = null;
            this._boundSelectionChangeHandler = null;
            this._manualHeightPx = 0;
            this._pointerDownHeight = 0;
            this._manualResizeLocked = false;
            this._lastKnownEditorHeight = 0;
            this._isAutoSizing = false;
            this._selectionDebug = false;
            this._lastModalTrigger = null;
            this._autoGrowEnabled = false;
            this._autoGrowMinHeight = 0;
            this._autoGrowMaxHeight = 0;
            this.currentView = 'compose';
            this.isRTL = false;
            this.history = [];
            this.historyIndex = -1;
            this.sourceView = null;
            this.historyView = null;
            this.toolbar = null;
            this.wrapper = null;
            this.hiddenInput = null;
            this.editor = null;
        }

        /**
         * Reset all flags
         * @private
         */
        _resetAllFlags() {
            this._selectionFinal = false;
            this._inIMEComposition = false;
            this._finalizationInProgress = false;
            this._finalizePending = false;
        }

        // =========================================================================
        // STATIC METHODS
        // =========================================================================

        /**
         * Check if all helper modules are loaded successfully.
         * @static
         * @returns {boolean}
         */
        static isHelpersReady() {
            if (!this._helpersLoaded || typeof this._helpersLoaded !== 'object') {
                return false;
            }
            return Object.values(this._helpersLoaded).every((v) => v === true);
        }

        /**
         * Get names of helper installers that failed or are missing.
         * @static
         * @returns {string[]}
         */
        static getMissingHelpers() {
            if (!this._helpersLoaded || typeof this._helpersLoaded !== 'object') {
                return [];
            }
            return Object.entries(this._helpersLoaded)
                .filter(([, loaded]) => loaded !== true)
                .map(([name]) => name);
        }

        /**
         * Helper-safe factory method.
         * @static
         * @returns {Promise<RichTextEditor>}
         */
        static async create(editorId, options = {}) {
            const helperResult = await this.loadHelpers();
            const missing = Object.entries(helperResult)
                .filter(([, loaded]) => loaded !== true)
                .map(([name]) => name);

            if (missing.length > 0 && !options.allowPartialHelpers) {
                throw new Error(
                    `RTE.create("${editorId}") aborted: helpers failed to load (${missing.join(', ')}).`
                );
            }

            return new RichTextEditor(editorId, {
                ...options,
                skipHelperCheck: missing.length === 0 || !!options.allowPartialHelpers
            });
        }

        /**
         * Load and install helper modules
         * @static
         * @returns {Promise<Object>}
         */
        static async loadHelpers(options = {}) {
            const forceReload = !!options.forceReload;
            if (this._helpersLoaded && !forceReload) return this._helpersLoaded;
            if (this._helpersLoadPromise && !forceReload) return this._helpersLoadPromise;
            if (forceReload) {
                this._helpersLoaded = null;
            }

            this._helpersLoadPromise = (async () => {
                const modules = [
                    ['./editor.utils.js', 'installUtilsHelpers'],
                    ['./editor.color.js', 'installColorHelpers'],
                    ['./editor.toolbar.js', 'installToolbarHelpers'],
                    ['./editor.selection.js', 'installSelectionManager'],
                    ['./editor.block-formatting.js', 'installBlockFormattingHelpers'],
                    ['./editor.normalization.js', 'installNormalizationHelpers'],
                    ['./editor.history.js', 'installHistoryHelpers'],
                    ['./editor.formatting.js', 'installFormattingHelpers'],
                    ['./editor.views.js', 'installViewHelpers'],
                    ['./editor.ui.js', 'installUIHelpers'],
                    ['./editor.modals.js', 'installModalHelpers'],
                    ['./editor.sanitize.js', 'installSanitizeHelpers'],
                    ['./editor.images.js', 'installImageHelpers'],
                    ['./editor.input.js', 'installInputHelpers'],
                    ['./editor.dragdrop.js', 'installDragDropHelpers'],
                    ['./editor.figures.js', 'installFigureHelpers'],
                    ['./editor.keyboard.js', 'installKeyboardHelpers']
                ];

                const result = {};
                const scriptInfo = this._getEditorScriptInfo();
                const editorBaseDir = scriptInfo.baseDir;
                const versionQuery = scriptInfo.versionQuery;

                window.RTE_debugLog('loader', `Loading helper modules from ${editorBaseDir}${versionQuery || ''}`);

                for (const [path, fn] of modules) {
                    try {
                        const loaded = await this._loadModule(editorBaseDir, path, fn, versionQuery);
                        result[fn] = loaded;
                    } catch (err) {
                        console.error(`RTE: Failed to load ${path}:`, err.message);
                        result[fn] = false;
                    }
                }

                this._helpersLoaded = result;

                const passed = Object.values(result).filter(v => v).length;
                const total = Object.values(result).length;
                window.RTE_debugLog('loader', `Helpers loaded: ${passed}/${total} successful`);
                return result;
            })();

            try {
                return await this._helpersLoadPromise;
            } finally {
                this._helpersLoadPromise = null;
            }
        }

        /**
         * Get editor script info (base path + optional version query)
         * @static
         * @private
         * @returns {{baseDir: string, versionQuery: string}}
         */
        static _getEditorScriptInfo() {
            const scripts = document.getElementsByTagName('script');

            for (let i = scripts.length - 1; i >= 0; i--) {
                if (scripts[i].src && scripts[i].src.includes('editor.js')) {
                    try {
                        const scriptUrl = new URL(scripts[i].src, window.location.href);
                        const baseDir = scriptUrl.href.replace(/\/[^\/?#]+(\?.*)?$/, '/');
                        const version = scriptUrl.searchParams.get('v');
                        const versionQuery = version ? `?v=${encodeURIComponent(version)}` : '';
                        return { baseDir, versionQuery };
                    } catch (err) {
                        const src = scripts[i].src;
                        const [path, query = ''] = src.split('?');
                        const baseDir = path.replace(/\/[^\/?#]+$/, '/');
                        const match = query.match(/(?:^|&)v=([^&]+)/);
                        const versionQuery = match ? `?v=${encodeURIComponent(match[1])}` : '';
                        return { baseDir, versionQuery };
                    }
                }
            }

            return { baseDir: './', versionQuery: '' };
        }

        /**
         * Load a single module
         * @static
         * @private
         * @returns {Promise<boolean>}
         */
        static async _loadModule(baseDir, path, functionName, versionQuery = '') {
            const fullPath = baseDir + path.replace(/^\.\//, '');
            const fullPathWithVersion = versionQuery ? `${fullPath}${versionQuery}` : fullPath;

            window.RTE_debugLog('loader', `Loading ${functionName} from ${fullPathWithVersion}`);

            if (typeof window[functionName] === 'function') {
                try {
                    window[functionName](RichTextEditor);
                    window.RTE_debugLog('loader', `${functionName} already available; installed`);
                    return true;
                } catch (installErr) {
                    console.error(`RTE: ${functionName} installation error:`, installErr);
                    return false;
                }
            }

            if (!this._moduleLoadPromises) {
                this._moduleLoadPromises = {};
            }

            if (!this._moduleLoadPromises[fullPath]) {
                this._moduleLoadPromises[fullPath] = new Promise((resolve) => {
                    const existingScript = document.querySelector(`script[data-rte-helper="${fullPath}"]`);

                    if (existingScript) {
                        if (existingScript.dataset.loaded === '1') {
                            resolve(true);
                            return;
                        }

                        existingScript.addEventListener('load', () => resolve(true), { once: true });
                        existingScript.addEventListener('error', () => resolve(false), { once: true });
                        return;
                    }

                    const script = document.createElement('script');
                    script.src = fullPathWithVersion;
                    script.type = 'text/javascript';
                    script.async = true;
                    script.setAttribute('data-rte-helper', fullPath);
                    script.dataset.loaded = '0';
                    script.onload = () => {
                        script.dataset.loaded = '1';
                        window.RTE_debugLog('loader', `Script loaded: ${fullPath}`);
                        resolve(true);
                    };
                    script.onerror = (err) => {
                        console.error(`RTE: Script load error: ${fullPath}`, err);
                        resolve(false);
                    };
                    document.head.appendChild(script);
                });
            }

            const loaded = await this._moduleLoadPromises[fullPath];
            if (!loaded) {
                delete this._moduleLoadPromises[fullPath];
            }

            if (loaded && typeof window[functionName] === 'function') {
                try {
                    window[functionName](RichTextEditor);
                    window.RTE_debugLog('loader', `${functionName} installed successfully`);
                    return true;
                } catch (installErr) {
                    console.error(`RTE: ${functionName} installation error:`, installErr);
                    return false;
                }
            }

            console.warn(`RTE: ${functionName} not found after loading`);
            return false;
        }
    }

    // =============================================================================
    // EXPORTS
    // =============================================================================

    // Attach to window
    if (_RTE_shouldDefine) {
        if (typeof window !== 'undefined') {
            // convenience methods on the constructor
            RichTextEditor.enableDebug = function (flag) {
                window.RTE_DEBUG = !!flag;
            };
            RichTextEditor.isDebug = function () {
                return !!window.RTE_DEBUG;
            };
            window.RichTextEditor = RichTextEditor;
        }
        if (typeof module !== 'undefined' && module.exports) {
            module.exports = RichTextEditor;
        }
    }

    // =============================================================================
    // AUTO-INITIALIZATION
    // =============================================================================

    if (typeof document !== 'undefined') {
        document.addEventListener('DOMContentLoaded', async () => {
            try {
                // Load helper modules
                const loadResult = await RichTextEditor.loadHelpers();

                // Wait briefly for DOMPurify to load (if the sanitize helper started loading it).
                try {
                    if (window.RTE_DOMPurifyPromise) {
                        const dom = await Promise.race([
                            window.RTE_DOMPurifyPromise,
                            new Promise(resolve => setTimeout(() => resolve(null), 3000))
                        ]);
                        if (dom) {
                            window.RTE_debugLog('loader', 'DOMPurify loaded and ready');
                        } else {
                            window.RTE_debugLog('loader', 'DOMPurify not available; using builtin sanitizer');
                        }
                    }
                } catch (e) {
                    console.warn('RTE: DOMPurify wait failed', e);
                }

                // Run smoke test if requested
                try {
                    const query = window.location?.search || '';
                    const hash = window.location?.hash || '';

                    if (query.indexOf('rte_smoketest=1') !== -1 || hash.indexOf('#rte-smoke') !== -1) {
                        const mod = await import('./editor.smoketest.js');
                        if (mod?.runEditorSmokeTest) {
                            mod.runEditorSmokeTest(loadResult);
                        }
                    }
                } catch (e) {
                    console.warn('RTE: Smoke test failed to run', e);
                }
            } catch (e) {
                console.warn('RTE: loadHelpers failed; editors may not function correctly', e);
            }

            // Auto-initialize all editors
            const editors = document.querySelectorAll('[id$="-wrapper"][data-rtl]');

            editors.forEach(wrapper => {
                const editorId = wrapper.id.replace('-wrapper', '');
                const globalVarName = `editor_${editorId}`;

                if (!window[globalVarName]) {
                    try {
                        window[globalVarName] = new RichTextEditor(editorId);

                        // Verify production ready
                        if (typeof window[globalVarName].verifyProductionReady === 'function') {
                            const isReady = window[globalVarName].verifyProductionReady();
                            if (!isReady) {
                                console.warn(`⚠️ Editor ${editorId} may have missing components`);
                            }
                        }
                    } catch (e) {
                        console.error(`Failed to initialize editor ${editorId}:`, e);
                    }
                }
            });
        });
    }

    // Export for modules
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = RichTextEditor;
    }

} // End of _RTE_shouldDefine check
