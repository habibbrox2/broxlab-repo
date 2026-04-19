/**
 * UIController - DOM Manipulation & Event Binding
 * Path: /public_html/ai/js/modules/ui/UIController.js
 *
 * Responsible for:
 *  - Rendering messages
 *  - Managing DOM updates
 *  - Event delegation
 *  - Scroll management
 */

export class UIController {
    constructor(domElement, stateManager, config = {}) {
        this.dom = domElement;
        this.state = stateManager;
        this.config = {
            messageSelector: '.brox-ai-msg',
            inputSelector: '[data-role="chat-input"]',
            sendButtonSelector: '[data-action="send"]',
            ...config,
        };

        this.cachedElements = new Map();
        this.isScrolledToBottom = true;

        this.cacheElements();
        this.setupScrollListener();
    }

    /**
     * Cache frequently accessed DOM elements
     */
    cacheElements() {
        const selectors = {
            messagesContainer: '[data-role="messages-container"]',
            inputField: '[data-role="chat-input"]',
            sendButton: '[data-action="send"]',
            statusIndicator: '[data-role="status"]',
            loadingIndicator: '[data-role="loading"]',
            errorContainer: '[data-role="errors"]',
        };

        Object.entries(selectors).forEach(([key, selector]) => {
            const el = this.dom.querySelector(selector);
            if (el) {
                this.cachedElements.set(key, el);
            }
        });
    }

    /**
     * Get cached element
     */
    getElement(key) {
        return this.cachedElements.get(key);
    }

    /**
     * Render a message in the DOM
     */
    renderMessage(message, index = null) {
        const container = this.getElement('messagesContainer');
        if (!container) return;

        const messageEl = this.createMessageElement(message);

        if (index !== null) {
            const messages = container.querySelectorAll(this.config.messageSelector);
            if (messages[index]) {
                messages[index].replaceWith(messageEl);
            } else {
                container.appendChild(messageEl);
            }
        } else {
            container.appendChild(messageEl);
        }

        this.scrollToBottom();
    }

    /**
     * Create message DOM element
     */
    createMessageElement(message) {
        const { role, content } = message;

        const div = document.createElement('div');
        div.className = `brox-ai-msg brox-ai-${role}`;
        div.setAttribute('data-role', role);

        // Avatar
        const avatar = document.createElement('div');
        avatar.className = 'brox-ai-msg-avatar';
        avatar.innerHTML = this.getAvatarIcon(role);
        div.appendChild(avatar);

        // Content
        const contentDiv = document.createElement('div');
        contentDiv.className = 'brox-ai-msg-content';

        if (Array.isArray(content)) {
            content.forEach((part) => {
                const el = this.renderMessagePart(part);
                if (el) contentDiv.appendChild(el);
            });
        } else if (typeof content === 'string') {
            contentDiv.innerHTML = this.formatMessage(content);
        }

        div.appendChild(contentDiv);

        // Metadata
        const meta = document.createElement('div');
        meta.className = 'brox-ai-msg-meta';
        meta.textContent = new Date().toLocaleTimeString();
        div.appendChild(meta);

        // Actions (for assistant messages)
        if (role === 'assistant') {
            const actions = this.createMessageActions(message);
            if (actions) div.appendChild(actions);
        }

        return div;
    }

    /**
     * Render message content part (text, image, file, etc)
     */
    renderMessagePart(part) {
        if (!part || typeof part !== 'object') return null;

        if (part.type === 'text' && typeof part.text === 'string') {
            const span = document.createElement('span');
            span.innerHTML = this.formatMessage(part.text);
            return span;
        }

        if (part.type === 'image_url' && part.image_url) {
            const wrap = document.createElement('div');
            wrap.className = 'brox-ai-msg-image-wrap';

            const img = document.createElement('img');
            img.src = part.image_url.url;
            img.alt = part.image_url.name || 'image';
            img.className = 'brox-ai-msg-image';
            wrap.appendChild(img);

            return wrap;
        }

        if (part.type === 'file' && part.file) {
            const wrap = document.createElement('div');
            wrap.className = 'brox-ai-msg-file-wrap';
            wrap.innerHTML = `<i class="bi bi-file"></i> <span>${this.escapeHtml(part.file.filename)}</span>`;
            return wrap;
        }

        return null;
    }

    /**
     * Get avatar icon for role
     */
    getAvatarIcon(role) {
        const icons = {
            user: '<i class="bi bi-person-fill"></i>',
            assistant: '<i class="bi bi-stars"></i>',
            system: '<i class="bi bi-exclamation-triangle"></i>',
        };
        return icons[role] || icons.system;
    }

    /**
     * Create message action buttons
     */
    createMessageActions(message) {
        const div = document.createElement('div');
        div.className = 'brox-ai-msg-actions';

        const actions = [
            { icon: 'bi-clipboard', label: 'Copy', action: 'copy' },
            { icon: 'bi-arrow-repeat', label: 'Regenerate', action: 'regenerate' },
            { icon: 'bi-pencil', label: 'Edit', action: 'edit' },
        ];

        actions.forEach(({ icon, label, action }) => {
            const btn = document.createElement('button');
            btn.className = 'brox-ai-msg-action-btn';
            btn.title = label;
            btn.innerHTML = `<i class="bi ${icon}"></i>`;
            btn.addEventListener('click', () => this.handleMessageAction(action, message));
            div.appendChild(btn);
        });

        return div;
    }

    /**
     * Handle message action
     */
    handleMessageAction(action, message) {
        console.log('[UIController] Message action:', { action, message });
        this.state.emit(`message:action:${action}`, message);
    }

    /**
     * Format message text (markdown, sanitization, etc)
     */
    formatMessage(text) {
        if (!text) return '';

        // Escape HTML
        text = this.escapeHtml(text);

        // Simple markdown-like formatting
        text = text
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/`(.*?)`/g, '<code>$1</code>')
            .replace(/\n/g, '<br>');

        return text;
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
     * Setup scroll listener
     */
    setupScrollListener() {
        const container = this.getElement('messagesContainer');
        if (!container) return;

        container.addEventListener('scroll', () => {
            const atBottom =
                container.scrollHeight - container.scrollTop - container.clientHeight < 10;
            this.isScrolledToBottom = atBottom;
        });
    }

    /**
     * Scroll to bottom
     */
    scrollToBottom() {
        if (!this.isScrolledToBottom) return;

        const container = this.getElement('messagesContainer');
        if (container) {
            setTimeout(() => {
                container.scrollTop = container.scrollHeight;
            }, 0);
        }
    }

    /**
     * Show loading indicator
     */
    showLoading() {
        const loading = this.getElement('loadingIndicator');
        if (loading) {
            loading.classList.remove('d-none');
        }
    }

    /**
     * Hide loading indicator
     */
    hideLoading() {
        const loading = this.getElement('loadingIndicator');
        if (loading) {
            loading.classList.add('d-none');
        }
    }

    /**
     * Show error message
     */
    showError(errorMessage) {
        const container = this.getElement('errorContainer');
        if (!container) return;

        const errorEl = document.createElement('div');
        errorEl.className = 'alert alert-danger';
        errorEl.innerHTML = `
            <strong>Error:</strong> ${this.escapeHtml(errorMessage)}
            <button type="button" class="btn-close" data-dismiss="alert"></button>
        `;

        container.appendChild(errorEl);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            errorEl.remove();
        }, 5000);
    }

    /**
     * Update status indicator
     */
    updateStatus(status, message) {
        const indicator = this.getElement('statusIndicator');
        if (!indicator) return;

        indicator.className = `brox-ai-status-indicator ${status}`;
        indicator.title = message;
        indicator.textContent = message;
    }

    /**
     * Get input value
     */
    getInputValue() {
        const input = this.getElement('inputField');
        return input ? input.value : '';
    }

    /**
     * Set input value
     */
    setInputValue(value) {
        const input = this.getElement('inputField');
        if (input) {
            input.value = value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    /**
     * Clear input
     */
    clearInput() {
        this.setInputValue('');
    }

    /**
     * Focus input
     */
    focusInput() {
        const input = this.getElement('inputField');
        if (input) {
            input.focus();
        }
    }

    /**
     * Clear all messages
     */
    clearMessages() {
        const container = this.getElement('messagesContainer');
        if (container) {
            container.innerHTML = '';
        }
    }

    /**
     * Get all messages from DOM
     */
    getMessages() {
        const container = this.getElement('messagesContainer');
        if (!container) return [];

        return Array.from(container.querySelectorAll(this.config.messageSelector)).map((el) => {
            const role = el.getAttribute('data-role');
            const content = el.querySelector('.brox-ai-msg-content')?.textContent || '';
            return { role, content };
        });
    }
}

export default UIController;
