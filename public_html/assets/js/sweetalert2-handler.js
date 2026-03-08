/**
 * SweetAlert2 Global Message Handler v3.0
 * 
 * Usage:
 *   window.showMessage('text', 'success');
 *   window.showAlert('title', 'message');
 *   await window.showConfirm('Sure?', 'title');
 *   const name = await window.showPrompt('Enter name:', '');
 */

(function () {
    'use strict';

    // configuration and logging helper
    const MessageConfig = {
        // enable or disable console logging for debugging purposes
        // can be toggled at runtime via MessageHandler.enableLogs()/disableLogs()
        enableLogs: false,

        /**
         * Central Configuration
         */
        // SweetAlert2 position for toasts
        toastPosition: 'top-right', // top-start, top-center, top-end, center-start, center, center-end, bottom-start, bottom-center, bottom-end

        // Animation timing
        toastDuration: 4000, // ms, 0 = manual dismiss
        animationDuration: 400, // ms

        // Theme colors
        successColor: '#198754', // Bootstrap green
        dangerColor: '#dc3545', // Bootstrap red
        warningColor: '#ffc107', // Bootstrap warning
        infoColor: '#0dcaf0', // Bootstrap info
        primaryColor: '#0d6efd', // Bootstrap primary

        // Accessibility
        announceToasts: true, // Screen reader announcements
        closeOnEscape: true, // Escape key closes modals

        // Additional options
        allowHtml: false, // HTML content (unsafe by default)
        showConfirmButton: true,
        showCancelButton: false
    };

    // simple wrapper that respects MessageConfig.enableLogs
    function log(...args) {
        if (MessageConfig.enableLogs && console && typeof console.log === 'function') {
            console.log(...args);
        }
    }

    /**
     * Get color based on status type
     */
    function getColorByStatus(status) {
        const colorMap = {
            'success': MessageConfig.successColor,
            'danger': MessageConfig.dangerColor,
            'error': MessageConfig.dangerColor,
            'warning': MessageConfig.warningColor,
            'info': MessageConfig.infoColor,
            'primary': MessageConfig.primaryColor
        };
        return colorMap[status] || colorMap['info'];
    }

    /**
     * Get icon based on status type
     */
    function getIconByStatus(status) {
        const iconMap = {
            'success': 'success',
            'danger': 'error',
            'error': 'error',
            'warning': 'warning',
            'info': 'info',
            'primary': 'info'
        };
        return iconMap[status] || 'info';
    }

    /**
     * Split custom helper options from native SweetAlert2 options.
     * Prevents unknown parameter warnings in SweetAlert2.
     */
    function splitSwalOptions(options = {}, customKeys = []) {
        const custom = {};
        const swalOptions = {};

        Object.entries(options || {}).forEach(([key, value]) => {
            if (customKeys.includes(key)) {
                custom[key] = value;
            } else {
                swalOptions[key] = value;
            }
        });

        return { custom, swalOptions };
    }

    /**
     * Toast notification (temporary popup)
     * @param {string} message - The message text
     * @param {string} status - 'success', 'danger', 'warning', 'info'
     * @param {number} duration - Auto-dismiss time in ms (0 = manual)
     * @param {object} options - Additional SweetAlert2 options
     */
    window.showMessage = async function (message, status = 'info', duration = MessageConfig.toastDuration, options = {}) {
        if (!message) return;

        const { custom, swalOptions } = splitSwalOptions(options, ['allowHtml']);
        const allowHtml = custom.allowHtml === true;
        const content = String(message);
        const icon = getIconByStatus(status);
        const config = {
            toast: true,
            position: MessageConfig.toastPosition,
            icon: icon,
            title: allowHtml ? undefined : content,
            html: allowHtml ? content : undefined,
            text: undefined,
            showConfirmButton: false,
            showCloseButton: true,
            closeButtonAriaLabel: 'Close',
            timer: duration > 0 ? duration : undefined,
            timerProgressBar: duration > 0,
            didOpen: async (toast) => {
                // Announce to screen readers
                if (MessageConfig.announceToasts) {
                    announceToScreenReader(`${status}: ${message}`);
                }
            },
            customClass: {
                container: 'swal2-message-container',
                popup: `swal2-popup-${status}`,
                title: 'swal2-message-title'
            },
            ...swalOptions
        };

        return await Swal.fire(config);
    };

    /**
     * Toast alias for backward compatibility
     */
    window.showToast = function (message, status = 'info', duration = MessageConfig.toastDuration, options = {}) {
        return window.showMessage(message, status, duration, options);
    };

    /**
     * Alert dialog
     * @param {string} message - Message text
     * @param {string} title - Dialog title (optional)
     * @param {string} status - 'success', 'danger', 'warning', 'info'
     * @param {object} options - Additional SweetAlert2 options
     */
    window.showAlert = async function (message, title = 'Alert', status = 'info', options = {}) {
        if (!message) return;

        const { custom, swalOptions } = splitSwalOptions(options, ['allowHtml']);
        const allowHtml = custom.allowHtml === true;
        const icon = getIconByStatus(status);
        const config = {
            icon: icon,
            title: String(title),
            html: allowHtml ? String(message) : undefined,
            text: allowHtml ? undefined : String(message),
            confirmButtonText: 'OK',
            confirmButtonColor: getColorByStatus(status),
            allowOutsideClick: false,
            allowEscapeKey: MessageConfig.closeOnEscape,
            position: 'center',  // Center position
            didOpen: async (modal) => {
                if (MessageConfig.announceToasts) {
                    announceToScreenReader(`Alert: ${title}. ${message}`);
                }
            },
            customClass: {
                popup: `swal2-alert-${status}`,
                title: 'swal2-alert-title',
                confirmButton: 'swal2-button-confirm'
            },
            ...swalOptions
        };

        return await Swal.fire(config);
    };

    /**
     * Confirmation dialog
     * @param {string} message - Message text
     * @param {string} title - Dialog title (optional)
     * @param {string} status - Status type for colors
     * @param {object} options - Additional SweetAlert2 options
     * @returns {Promise<boolean>} - true if confirmed, false if cancelled
     */
    window.showConfirm = async function (message, title = 'Confirm', status = 'warning', options = {}) {
        if (!message) return false;

        const { custom, swalOptions } = splitSwalOptions(options, ['allowHtml']);
        const allowHtml = custom.allowHtml === true;
        const icon = getIconByStatus(status);
        const config = {
            icon: icon,
            title: String(title),
            html: allowHtml ? String(message) : undefined,
            text: allowHtml ? undefined : String(message),
            showCancelButton: true,
            confirmButtonText: 'Yes, Proceed',
            confirmButtonColor: getColorByStatus(status),
            cancelButtonText: 'Cancel',
            cancelButtonColor: '#6c757d',
            allowOutsideClick: false,
            allowEscapeKey: MessageConfig.closeOnEscape,
            position: 'center',  // Center position
            didOpen: async (modal) => {
                if (MessageConfig.announceToasts) {
                    announceToScreenReader(`Confirmation required: ${title}. ${message}`);
                }
            },
            customClass: {
                popup: `swal2-confirm-${status}`,
                title: 'swal2-confirm-title',
                confirmButton: 'swal2-button-confirm',
                cancelButton: 'swal2-button-cancel'
            },
            ...swalOptions
        };

        const result = await Swal.fire(config);
        return result.isConfirmed ?? false;
    };

    /**
     * Prompt for user input
     * @param {string} message - Prompt message
     * @param {string} defaultValue - Default input value
     * @param {string} label - Input label (optional)
     * @param {object} options - Additional SweetAlert2 options
     * @returns {Promise<string|null>} - User input or null if cancelled
     */
    window.showPrompt = async function (message, defaultValue = '', label = '', options = {}) {
        if (!message) return null;

        const { custom, swalOptions } = splitSwalOptions(options, ['required']);
        const isRequired = custom.required === true;
        const config = {
            title: String(label || 'Please Enter'),
            html: String(message),
            input: 'text',
            inputValue: String(defaultValue),
            inputAttributes: {
                'aria-label': String(label || message),
                'placeholder': String(defaultValue || 'Enter text...')
            },
            showCancelButton: true,
            confirmButtonText: 'Submit',
            confirmButtonColor: MessageConfig.primaryColor,
            cancelButtonText: 'Cancel',
            cancelButtonColor: '#6c757d',
            allowOutsideClick: false,
            allowEscapeKey: MessageConfig.closeOnEscape,
            position: 'center',  // Center position
            inputValidator: (value) => {
                if (!value && isRequired) {
                    return 'This field is required';
                }
            },
            didOpen: async (modal) => {
                const input = modal.querySelector('input');
                if (input) {
                    input.focus();
                    input.select();
                }
                if (MessageConfig.announceToasts) {
                    announceToScreenReader(`Prompt: ${message}`);
                }
            },
            customClass: {
                popup: 'swal2-prompt',
                title: 'swal2-prompt-title',
                input: 'swal2-prompt-input',
                confirmButton: 'swal2-button-confirm',
                cancelButton: 'swal2-button-cancel'
            },
            ...swalOptions
        };

        const result = await Swal.fire(config);
        return result.value ?? null;
    };

    /**
     * Show validation errors
     */
    window.showValidationErrors = function (errors = []) {
        if (!Array.isArray(errors) || errors.length === 0) return;

        errors.forEach((error, index) => {
            // Slight delay between toasts for visual effect
            setTimeout(() => {
                window.showMessage(error, 'danger', 5000);
            }, index * 300);
        });
    };

    /**
     * Handle AJAX success
     */
    window.handleAjaxSuccess = function (data, userMessage = '') {
        const message = userMessage || data.message || 'Success';
        const status = data.status || 'success';
        window.showMessage(message, status, 5000);
    };

    /**
     * Handle AJAX error
     */
    window.handleAjaxError = function (error, userMessage = '') {
        const message = userMessage || error.message || 'An error occurred. Please try again.';
        window.showMessage(message, 'danger', 5000);
    };

    /**
     * Announce message to screen readers
     */
    function announceToScreenReader(message) {
        if (!message) return;

        const liveRegion = document.getElementById('sr-live-region') || createLiveRegion();
        liveRegion.setAttribute('aria-live', 'assertive');
        liveRegion.setAttribute('aria-atomic', 'true');
        liveRegion.textContent = message;

        // Clear after announcement
        setTimeout(() => {
            liveRegion.textContent = '';
        }, 2000);
    }

    /**
     * Create screen reader live region
     */
    function createLiveRegion() {
        const region = document.createElement('div');
        region.id = 'sr-live-region';
        region.className = 'visually-hidden';
        region.setAttribute('role', 'status');
        region.setAttribute('aria-live', 'polite');
        document.body.appendChild(region);
        return region;
    }

    /**
     * ========================================
     * CONFIGURATION API
     * ========================================
     */
    window.MessageHandlerConfig = {
        set(key, value) {
            if (key in MessageConfig) {
                MessageConfig[key] = value;
            } else {
                console.warn(`Unknown config key: ${key}`);
            }
        },

        get(key) {
            return MessageConfig[key];
        },

        setAll(config) {
            Object.assign(MessageConfig, config);
        },

        getAll() {
            return { ...MessageConfig };
        }
    };

    /**
     * ========================================
     * MESSAGE HANDLER OBJECT
     * ========================================
     */
    window.MessageHandler = {
        /**
         * Initialize the SweetAlert2 message handler
         */
        init: function (config = null) {
            // Merge any server-provided config
            if (config && typeof config === 'object') {
                MessageHandlerConfig.setAll(config);
            }

            // Display server-side flash queue (or fallback single flash).
            const flashQueue = Array.isArray(window.__INITIAL_FLASH_QUEUE)
                ? window.__INITIAL_FLASH_QUEUE.slice()
                : [];

            if (flashQueue.length === 0 && window.__INITIAL_FLASH) {
                flashQueue.push(window.__INITIAL_FLASH);
            }

            if (flashQueue.length > 0) {
                let renderedAnyFlash = false;

                flashQueue.forEach((flash, index) => {
                    if (!flash || (!flash.text && !flash.message)) {
                        return;
                    }

                    const msg = flash.text || flash.message;
                    const status = flash.status || flash.type || 'info';
                    const duration = flash.duration || MessageConfig.toastDuration;

                    setTimeout(() => {
                        window.showMessage(msg, status, duration);
                    }, index * 160);

                    renderedAnyFlash = true;
                });

                if (renderedAnyFlash) {
                    window.__FLASH_RENDERED_ON_LOAD = true;
                }
            }

            delete window.__INITIAL_FLASH;
            delete window.__INITIAL_FLASH_QUEUE;

            log('✅ SweetAlert2 Message Handler v3.0 initialized');
        },

        getConfig: function () {
            return MessageHandlerConfig.getAll();
        },

        setConfig: function (key, value) {
            if (typeof key === 'object') {
                MessageHandlerConfig.setAll(key);
            } else {
                MessageHandlerConfig.set(key, value);
            }
        },

        /**
         * Show a loading state (no auto-dismiss)
         */
        showLoading: async function (message = 'Loading...', title = '') {
            return await Swal.fire({
                icon: 'info',
                title: title,
                html: message,
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        },

        /**
         * Hide the loading state
         */
        hideLoading: function () {
            Swal.hideLoading();
            Swal.close();
        },

        /* logging controls */
        enableLogs: function () {
            MessageConfig.enableLogs = true;
        },
        disableLogs: function () {
            MessageConfig.enableLogs = false;
        },
        logsEnabled: function () {
            return !!MessageConfig.enableLogs;
        }
    };

    // Auto-initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.__MESSAGE_HANDLER_INITIALIZED) {
                window.__MESSAGE_HANDLER_INITIALIZED = true;
                window.MessageHandler.init();
            }
        });
    } else {
        // DOM already loaded
        if (!window.__MESSAGE_HANDLER_INITIALIZED) {
            window.__MESSAGE_HANDLER_INITIALIZED = true;
            window.MessageHandler.init();
        }
    }

    /**
     * SweetAlert2 Custom Styling
     */
    const style = document.createElement('style');
    style.textContent = `
        /* SweetAlert2 Toast Customization */
        .swal2-popup.swal2-toast {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            padding: 1rem 1.5rem;
        }

        .swal2-popup.swal2-toast.swal2-popup-success {
            border-left: 4px solid #198754;
        }

        .swal2-popup.swal2-toast.swal2-popup-danger,
        .swal2-popup.swal2-toast.swal2-popup-error {
            border-left: 4px solid #dc3545;
        }

        .swal2-popup.swal2-toast.swal2-popup-warning {
            border-left: 4px solid #ffc107;
        }

        .swal2-popup.swal2-toast.swal2-popup-info {
            border-left: 4px solid #0dcaf0;
        }

        .swal2-message-title {
            font-size: 1rem;
            font-weight: 500;
            color: #212529;
        }

        /* SweetAlert2 Alert & Dialog Customization */
        .swal2-popup:not(.swal2-toast) {
            border-radius: 16px;
            border: none;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
            padding: 2rem;
        }

        .swal2-alert-title,
        .swal2-confirm-title,
        .swal2-prompt-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #212529;
            margin-bottom: 1rem;
        }

        .swal2-popup.swal2-alert-success {
            border-top: 5px solid #198754;
        }

        .swal2-popup.swal2-alert-danger,
        .swal2-popup.swal2-alert-error {
            border-top: 5px solid #dc3545;
        }

        .swal2-popup.swal2-alert-warning {
            border-top: 5px solid #ffc107;
        }

        .swal2-popup.swal2-alert-info {
            border-top: 5px solid #0dcaf0;
        }

        /* Buttons */
        .swal2-button-confirm {
            border-radius: 8px;
            padding: 0.75rem 2rem !important;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        .swal2-button-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }

        .swal2-button-cancel {
            border-radius: 8px;
            padding: 0.75rem 2rem !important;
            font-weight: 600;
            font-size: 0.95rem;
            background: #e9ecef !important;
            color: #495057 !important;
        }

        .swal2-button-cancel:hover {
            background: #dee2e6 !important;
        }

        /* Input Styling */
        .swal2-prompt-input {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .swal2-prompt-input:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 107, 253, 0.25);
        }

        /* Icon Customization */
        .swal2-icon {
            border-radius: 50%;
            border: 4px solid;
            animation: show-icon 0.5s ease-in-out;
        }

        .swal2-icon.swal2-success {
            border-color: #198754;
            color: #198754;
        }

        .swal2-icon.swal2-error {
            border-color: #dc3545;
            color: #dc3545;
        }

        .swal2-icon.swal2-warning {
            border-color: #ffc107;
            color: #ffc107;
        }

        .swal2-icon.swal2-info {
            border-color: #0dcaf0;
            color: #0dcaf0;
        }

        @keyframes show-icon {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }

        /* Accessibility */
        .visually-hidden {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border-width: 0;
        }

        /* Animation Support for prefers-reduced-motion */
        @media (prefers-reduced-motion: reduce) {
            .swal2-popup,
            .swal2-icon,
            .swal2-button-confirm,
            .swal2-button-cancel {
                animation: none !important;
                transition: none !important;
            }
        }
    `;
    document.head.appendChild(style);

})();
