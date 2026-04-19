/**
 * AI Assistant UI Enhancements
 * Path: /public_html/ai/js/modules/ui-enhancements.js
 * 
 * Features:
 *  - Dark mode toggle with localStorage persistence
 *  - Enhanced message rendering (timestamps, code highlighting)
 *  - Toast notifications
 *  - Loading skeletons
 *  - Error handling with retry
 *  - Connection status indicator
 */

export class UIEnhancements {
    constructor(config = {}) {
        this.config = {
            darkModeKey: 'brox.ai.darkMode',
            ...config,
        };

        this.isDarkMode = this.loadDarkModePreference();
        this.init();
    }

    /**
     * Initialize UI enhancements
     */
    init() {
        this.setupDarkModeToggle();
        this.setupToastContainer();
        this.watchSystemPreference();
    }

    /**
     * Load dark mode preference from localStorage
     */
    loadDarkModePreference() {
        const saved = localStorage.getItem(this.config.darkModeKey);
        if (saved !== null) {
            return JSON.parse(saved);
        }

        // Check system preference
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    /**
     * Save dark mode preference
     */
    saveDarkModePreference(isDark) {
        localStorage.setItem(this.config.darkModeKey, JSON.stringify(isDark));
    }

    /**
     * Toggle dark mode
     */
    toggleDarkMode() {
        this.isDarkMode = !this.isDarkMode;
        this.applyDarkMode(this.isDarkMode);
        this.saveDarkModePreference(this.isDarkMode);
        this.updateDarkModeToggleUI();
    }

    /**
     * Apply dark mode to DOM
     */
    applyDarkMode(isDark) {
        const root = document.documentElement;
        const shell = document.getElementById('adminAiShell');

        if (isDark) {
            root.classList.add('brox-ai-dark-mode');
            if (shell) shell.classList.add('brox-ai-dark-mode');
        } else {
            root.classList.remove('brox-ai-dark-mode');
            if (shell) shell.classList.remove('brox-ai-dark-mode');
        }
    }

    /**
     * Setup dark mode toggle button
     */
    setupDarkModeToggle() {
        const toggle = document.getElementById('adminAiDarkModeToggle');
        if (!toggle) return;

        // Set initial state
        this.updateDarkModeToggleUI();

        // Add click listener
        toggle.addEventListener('click', () => this.toggleDarkMode());
    }

    /**
     * Update toggle UI to reflect current state
     */
    updateDarkModeToggleUI() {
        const toggle = document.getElementById('adminAiDarkModeToggle');
        if (!toggle) return;

        if (this.isDarkMode) {
            toggle.classList.add('active');
            toggle.setAttribute('aria-pressed', 'true');
        } else {
            toggle.classList.remove('active');
            toggle.setAttribute('aria-pressed', 'false');
        }
    }

    /**
     * Watch for system preference changes
     */
    watchSystemPreference() {
        if (!window.matchMedia) return;

        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        mediaQuery.addEventListener('change', (e) => {
            // Only apply if user hasn't manually set preference
            if (localStorage.getItem(this.config.darkModeKey) === null) {
                this.isDarkMode = e.matches;
                this.applyDarkMode(this.isDarkMode);
            }
        });
    }

    /**
     * Setup toast notification container
     */
    setupToastContainer() {
        if (!document.getElementById('brox-ai-toast-container')) {
            const container = document.createElement('div');
            container.id = 'brox-ai-toast-container';
            container.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 10001;
        display: flex;
        flex-direction: column;
        gap: 8px;
        max-width: 320px;
        pointer-events: none;
      `;
            document.body.appendChild(container);
        }
    }

    /**
     * Show toast notification
     * @param {string} message - Notification message
     * @param {string} type - Type: success, error, warning, info
     * @param {number} duration - Duration in milliseconds
     */
    showToast(message, type = 'info', duration = 3000) {
        const container = document.getElementById('brox-ai-toast-container');
        if (!container) this.setupToastContainer();

        const toast = document.createElement('div');
        toast.className = `brox-ai-toast brox-ai-toast-${type}`;
        toast.style.cssText = `
      pointer-events: auto;
    `;

        const icon = this.getToastIcon(type);
        toast.innerHTML = `
      <div style="display: flex; align-items: center; gap: 12px;">
        ${icon}
        <span>${this.escapeHtml(message)}</span>
      </div>
    `;

        document.getElementById('brox-ai-toast-container').appendChild(toast);

        // Auto-remove after duration
        if (duration > 0) {
            setTimeout(() => {
                toast.remove();
            }, duration);
        }

        return toast;
    }

    /**
     * Get toast icon based on type
     */
    getToastIcon(type) {
        const icons = {
            success: '<i class="bi bi-check-circle-fill" style="color: var(--assistant-success)"></i>',
            error: '<i class="bi bi-exclamation-circle-fill" style="color: var(--assistant-error)"></i>',
            warning: '<i class="bi bi-exclamation-triangle-fill" style="color: var(--assistant-warning)"></i>',
            info: '<i class="bi bi-info-circle-fill" style="color: var(--assistant-accent)"></i>',
        };
        return icons[type] || icons.info;
    }

    /**
     * Format relative time (e.g., "2 minutes ago")
     */
    formatRelativeTime(date) {
        if (!(date instanceof Date)) {
            date = new Date(date);
        }

        const now = new Date();
        const secondsAgo = Math.floor((now - date) / 1000);

        if (secondsAgo < 60) return 'just now';
        if (secondsAgo < 3600) return `${Math.floor(secondsAgo / 60)}m ago`;
        if (secondsAgo < 86400) return `${Math.floor(secondsAgo / 3600)}h ago`;
        if (secondsAgo < 604800) return `${Math.floor(secondsAgo / 86400)}d ago`;

        return date.toLocaleDateString();
    }

    /**
     * Enhance message with timestamp
     */
    addMessageTimestamp(messageElement, date = new Date()) {
        if (messageElement.querySelector('.message-timestamp')) {
            return; // Already has timestamp
        }

        const timestamp = document.createElement('div');
        timestamp.className = 'message-timestamp';
        timestamp.textContent = this.formatRelativeTime(date);
        timestamp.title = date.toLocaleString();

        messageElement.appendChild(timestamp);
    }

    /**
     * Add copy button to code blocks
     */
    enhanceCodeBlocks(container) {
        const codeBlocks = container.querySelectorAll('pre');

        codeBlocks.forEach((block) => {
            if (block.querySelector('.code-copy-btn')) return; // Already enhanced

            const header = document.createElement('div');
            header.className = 'code-block-header';

            const lang = this.detectLanguage(block.textContent);
            const label = document.createElement('span');
            label.className = 'code-block-header-label';
            label.textContent = lang || 'code';

            const copyBtn = document.createElement('button');
            copyBtn.className = 'code-copy-btn';
            copyBtn.innerHTML = '<i class="bi bi-clipboard"></i> Copy';
            copyBtn.addEventListener('click', () => this.copyCodeToClipboard(block, copyBtn));

            header.appendChild(label);
            header.appendChild(copyBtn);

            block.parentNode.insertBefore(header, block);
            block.parentNode.style.position = 'relative';
        });
    }

    /**
     * Detect programming language from code block
     */
    detectLanguage(code) {
        if (code.includes('SELECT') || code.includes('INSERT')) return 'sql';
        if (code.includes('<?php')) return 'php';
        if (code.includes('import ') || code.includes('from ')) return 'python';
        if (code.includes('function ') || code.includes('const ')) return 'javascript';
        if (code.includes('interface ') || code.includes(': Type')) return 'typescript';
        if (code.includes('<!DOCTYPE')) return 'html';
        if (code.includes('{') && code.includes('}')) return 'json';
        return 'code';
    }

    /**
     * Copy code block to clipboard
     */
    async copyCodeToClipboard(codeBlock, button) {
        try {
            const code = codeBlock.textContent;
            await navigator.clipboard.writeText(code);

            // Show success state
            const originalHtml = button.innerHTML;
            button.innerHTML = '<i class="bi bi-check2"></i> Copied!';
            button.classList.add('copied');

            setTimeout(() => {
                button.innerHTML = originalHtml;
                button.classList.remove('copied');
            }, 2000);

            this.showToast('Code copied to clipboard', 'success', 2000);
        } catch (err) {
            this.showToast('Failed to copy code', 'error', 3000);
        }
    }

    /**
     * Show loading skeleton for messages
     */
    createMessageSkeleton() {
        const skeleton = document.createElement('div');
        skeleton.className = 'message message-skeleton';
        skeleton.innerHTML = `
      <div class="message-skeleton-title"></div>
      <div class="message-skeleton-text" style="width: 100%; height: 16px; margin: 8px 0;"></div>
      <div class="message-skeleton-text" style="width: 95%; height: 16px; margin: 8px 0;"></div>
      <div class="message-skeleton-text" style="width: 90%; height: 16px; margin: 8px 0;"></div>
    `;
        return skeleton;
    }

    /**
     * Show connection status
     */
    updateConnectionStatus(status = 'connected', message = 'Connected') {
        const statusEl = document.getElementById('adminAiStatusText');
        if (!statusEl) return;

        const statusDot = statusEl.querySelector('.brox-ai-status-dot') ||
            (() => {
                const dot = document.createElement('span');
                dot.className = 'brox-ai-status-dot';
                statusEl.insertBefore(dot, statusEl.firstChild);
                return dot;
            })();

        statusDot.className = `brox-ai-status-dot brox-ai-status-dot-${status}`;
        statusEl.textContent = message;
        statusEl.insertBefore(statusDot, statusEl.firstChild);
    }

    /**
     * Show error with retry
     */
    showErrorWithRetry(message, onRetry) {
        const errorEl = document.createElement('div');
        errorEl.className = 'brox-ai-error-message';
        errorEl.style.cssText = `
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 12px;
      background: var(--assistant-error);
      color: white;
      border-radius: 8px;
      margin: 8px 0;
    `;

        errorEl.innerHTML = `
      <span>${this.escapeHtml(message)}</span>
      <button class="brox-ai-error-retry-btn" style="
        background: rgba(255,255,255,0.2);
        border: 1px solid rgba(255,255,255,0.4);
        color: white;
        padding: 4px 12px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.75rem;
      ">Retry</button>
    `;

        errorEl.querySelector('.brox-ai-error-retry-btn').addEventListener('click', onRetry);

        return errorEl;
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Apply dark mode on initialization
     */
    applyInitialTheme() {
        if (this.isDarkMode) {
            this.applyDarkMode(true);
        }
    }
}

// Export for use in main script
export default UIEnhancements;
