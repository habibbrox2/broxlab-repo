/**
 * AdminAssistant Orchestrator - Phase 2 Main Coordinator
 * Path: /public_html/ai/js/modules/AdminAssistant.js
 *
 * Ties together all Phase 2 modules:
 *  - StateManager (state)
 *  - ChatService (API)
 *  - UIController (UI rendering)
 *  - CommandHandler (commands)
 *  - StorageManager (persistence)
 *  - ImageContextManager (images)
 *  - KeyboardHandler (keyboard)
 *  - EventEmitter (communication)
 */

import StateManager from './state/StateManager.js';
import ChatService from './services/ChatService.js';
import UIController from './ui/UIController.js';
import CommandHandler from './handlers/CommandHandler.js';
import StorageManager from './services/StorageManager.js';
import ImageContextManager from './handlers/ImageContextManager.js';
import KeyboardHandler from './handlers/KeyboardHandler.js';
import EventEmitter from './utils/EventEmitter.js';

export class AdminAssistant {
    constructor(containerElement, config = {}) {
        this.config = {
            autoInitialize: true,
            persistState: true,
            enableOffline: true,
            maxImageUploads: 5,
            ...config,
        };

        this.container = containerElement;
        this.emitter = new EventEmitter();

        // Initialize core modules
        this.state = new StateManager();
        this.chat = new ChatService();
        this.storage = new StorageManager();
        this.ui = new UIController(this.container, this.state);
        this.commands = new CommandHandler(this.state, this.chat);
        this.images = new ImageContextManager({ maxImages: this.config.maxImageUploads });
        this.keyboard = new KeyboardHandler();

        this.isInitialized = false;
        this.messageQueue = [];

        if (this.config.autoInitialize) {
            this.initialize();
        }
    }

    /**
     * Initialize assistant
     */
    async initialize() {
        try {
            console.log('[AdminAssistant] Initializing...');

            // Setup state listeners
            this._setupStateListeners();

            // Setup UI listeners
            this._setupUIListeners();

            // Setup chat listeners
            this._setupChatListeners();

            // Setup keyboard shortcuts
            this._setupKeyboardShortcuts();

            // Setup image listeners
            this._setupImageListeners();

            // Load persisted state if available
            if (this.config.persistState) {
                await this._loadPersistedState();
            }

            this.isInitialized = true;
            console.log('[AdminAssistant] Initialized successfully');
            this.emitter.emit('assistant:ready');

            return this;
        } catch (e) {
            console.error('[AdminAssistant] Initialization failed:', e);
            this.emitter.emit('assistant:error', e);
            throw e;
        }
    }

    /**
     * Setup state listeners
     */
    _setupStateListeners() {
        // Message added
        this.state.subscribe('message:added', (message) => {
            console.log('[AdminAssistant] Message added');
            this.emitter.emit('message:added', message);
        });

        // State changed
        this.state.subscribe('state:changed', (updates) => {
            if (this.config.persistState) {
                this.storage.setLocal('brox-ai-state', this.state.getState());
            }
            this.emitter.emit('state:changed', updates);
        });

        // Error occurred
        this.state.subscribe('error', (error) => {
            console.error('[AdminAssistant] State error:', error);
            this.ui.showError(error.message);
            this.emitter.emit('assistant:error', error);
        });

        // Loading state
        this.state.subscribe('loading', (isLoading) => {
            if (isLoading) {
                this.ui.showLoading();
            } else {
                this.ui.hideLoading();
            }
        });
    }

    /**
     * Setup UI listeners
     */
    _setupUIListeners() {
        // Get send button and input
        const sendBtn = this.container.querySelector('[data-action="send"]');
        const input = this.container.querySelector('[data-input="message"]');

        if (sendBtn) {
            sendBtn.addEventListener('click', () => this._handleSendMessage());
        }

        if (input) {
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this._handleSendMessage();
                }
            });

            input.addEventListener('input', () => {
                this.emitter.emit('input:changed', input.value);
            });
        }
    }

    /**
     * Setup chat listeners
     */
    _setupChatListeners() {
        // Online/offline status
        this.chat.on('status:changed', (status) => {
            this.ui.updateStatus(status.online ? 'online' : 'offline', status.message);
            this.emitter.emit('network:status', status);
        });

        // Queue updated
        this.chat.on('queue:updated', (queueLength) => {
            console.log('[AdminAssistant] Pending messages:', queueLength);
            this.emitter.emit('queue:updated', queueLength);
        });
    }

    /**
     * Setup keyboard shortcuts
     */
    _setupKeyboardShortcuts() {
        // Send message
        this.keyboard.on('send:message', () => {
            this._handleSendMessage();
        });

        // Focus input
        this.keyboard.on('focus:input', () => {
            this.ui.focusInput();
        });

        // Voice input
        this.keyboard.on('voice:toggle', () => {
            this.emitter.emit('voice:toggle');
        });

        // Command menu
        this.keyboard.on('command:open', () => {
            this.emitter.emit('command:menu:open');
        });

        // Clear chat
        this.keyboard.on('chat:clear', () => {
            if (confirm('Clear all messages?')) {
                this._handleClearChat();
            }
        });

        // Theme toggle
        this.keyboard.on('theme:toggle', () => {
            this.emitter.emit('theme:toggle');
        });

        // Export
        this.keyboard.on('export:conversation', () => {
            this._handleExportConversation();
        });

        // Search
        this.keyboard.on('search:open', () => {
            this.emitter.emit('search:open');
        });

        // Close modal
        this.keyboard.on('modal:close', () => {
            this.emitter.emit('modal:close');
        });
    }

    /**
     * Setup image listeners
     */
    _setupImageListeners() {
        // Image added
        this.images.on('image:added', (imageData) => {
            console.log('[AdminAssistant] Image added');
            this.emitter.emit('image:added', imageData);
            this.ui.updateStatus('success', `Image uploaded (${this.images.getImageCount()}/${this.config.maxImageUploads})`);
        });

        // Image removed
        this.images.on('image:removed', (imageData) => {
            console.log('[AdminAssistant] Image removed');
            this.emitter.emit('image:removed', imageData);
        });

        // OCR completed
        this.images.on('ocr:completed', ({ imageId, text }) => {
            console.log('[AdminAssistant] OCR completed:', imageId);
            this.emitter.emit('ocr:completed', { imageId, text });
        });

        // Image error
        this.images.on('image:error', (error) => {
            this.ui.showError(`Image error: ${error.message}`);
            this.emitter.emit('image:error', error);
        });
    }

    /**
     * Handle send message
     */
    async _handleSendMessage() {
        try {
            const text = this.ui.getInputValue();

            if (!text.trim()) {
                this.ui.showError('Cannot send empty message');
                return;
            }

            // Check if command
            if (this.commands.isCommand(text)) {
                await this._handleCommand(text);
                return;
            }

            // Add to state
            this.state.addMessage({
                role: 'user',
                content: text,
                timestamp: new Date().toISOString(),
            });

            this.ui.clearInput();

            // Send to server with streaming
            await this._sendMessage(text);
        } catch (e) {
            console.error('[AdminAssistant] Send message failed:', e);
            this.state.setError(e.message);
        }
    }

    /**
     * Send message to server
     */
    async _sendMessage(text) {
        try {
            this.state.setLoading(true);

            const payload = {
                message: text,
                conversationId: this.state.getState().currentConversationId,
                images: this.images.getImagesPayload(),
                settings: {
                    tone: this.state.getState().responseTone,
                    contextDepth: this.state.getState().contextDepth,
                },
            };

            let assistantMessage = {
                role: 'assistant',
                content: '',
                timestamp: new Date().toISOString(),
            };

            // Add placeholder message
            this.state.addMessage(assistantMessage);
            const messageIndex = this.state.getState().messages.length - 1;

            // Stream response
            await this.chat.sendMessageStream(
                payload,
                (chunk) => {
                    // Handle streaming chunk
                    assistantMessage.content += chunk.text || '';
                    this.state.updateMessage(messageIndex, { content: assistantMessage.content });
                    this.ui.renderMessage(assistantMessage);
                },
                () => {
                    // On complete
                    this.state.setLoading(false);
                    this.ui.scrollToBottom();

                    // Clear images after successful send
                    this.images.clearImages();

                    // Save conversation
                    if (this.config.persistState) {
                        const convo = this.state.getState();
                        this.storage.saveConversation(convo);
                    }

                    this.emitter.emit('message:complete', assistantMessage);
                },
                (error) => {
                    // On error
                    this.state.setError(error.message);
                    this.state.setLoading(false);
                    this.ui.showError(error.message);
                    this.emitter.emit('message:error', error);
                }
            );
        } catch (e) {
            this.state.setError(e.message);
            this.state.setLoading(false);
            this.ui.showError(e.message);
        }
    }

    /**
     * Handle command execution
     */
    async _handleCommand(text) {
        try {
            const parsed = this.commands.parseCommand(text);

            this.state.setLoading(true);
            this.ui.showLoading();

            const result = await this.commands.executeCommand(parsed.commandName, parsed.args);

            this.state.addMessage({
                role: 'system',
                content: JSON.stringify(result, null, 2),
                command: parsed.commandName,
                timestamp: new Date().toISOString(),
            });

            this.ui.clearInput();
            this.state.setLoading(false);
            this.ui.hideLoading();

            this.emitter.emit('command:executed', { command: parsed.commandName, result });
        } catch (e) {
            this.state.setError(e.message);
            this.state.setLoading(false);
            this.ui.showError(`Command failed: ${e.message}`);
            this.emitter.emit('command:error', e);
        }
    }

    /**
     * Handle clear chat
     */
    _handleClearChat() {
        this.state.clearMessages();
        this.ui.clearMessages();
        this.images.clearImages();
        this.storage.removeLocal('brox-ai-state');
        this.ui.showToast('Chat cleared', 'info');
        this.emitter.emit('chat:cleared');
    }

    /**
     * Handle export conversation
     */
    async _handleExportConversation() {
        try {
            const format = prompt('Export format: json, markdown, or pdf?', 'json');

            if (!format) return;

            this.state.setLoading(true);

            const convo = this.state.getState();
            const exported = await this.chat.exportConversation(convo.currentConversationId, format);

            this.state.setLoading(false);

            // Trigger download
            const blob = new Blob([exported], { type: 'application/octet-stream' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `conversation-${Date.now()}.${format}`;
            a.click();

            this.ui.showToast('Conversation exported', 'success');
            this.emitter.emit('export:completed', { format });
        } catch (e) {
            this.state.setError(e.message);
            this.ui.showError(`Export failed: ${e.message}`);
            this.emitter.emit('export:error', e);
        }
    }

    /**
     * Load persisted state
     */
    async _loadPersistedState() {
        try {
            const savedState = this.storage.getLocal('brox-ai-state');

            if (savedState) {
                this.state.importState(savedState);
                console.log('[AdminAssistant] Persisted state restored');
                this.emitter.emit('state:restored');
            }
        } catch (e) {
            console.error('[AdminAssistant] Failed to restore state:', e);
        }
    }

    /**
     * Add image
     */
    async addImage(source, metadata) {
        return await this.images.addImage(source, metadata);
    }

    /**
     * Send custom message
     */
    async sendMessage(text) {
        this.ui.setInputValue(text);
        await this._handleSendMessage();
    }

    /**
     * Execute command
     */
    async executeCommand(commandName, args) {
        return await this.commands.executeCommand(commandName, args);
    }

    /**
     * Get state slice
     */
    getState(path) {
        return path ? this.state.getStateSlice(path) : this.state.getState();
    }

    /**
     * Register event listener
     */
    on(eventName, callback) {
        return this.emitter.on(eventName, callback);
    }

    /**
     * Get keyboard shortcuts
     */
    getShortcuts() {
        return this.keyboard.getAllShortcuts();
    }

    /**
     * Get all images
     */
    getImages() {
        return this.images.getImages();
    }

    /**
     * Clear all images
     */
    clearImages() {
        this.images.clearImages();
    }

    /**
     * Export current conversation
     */
    exportConversation(format = 'json') {
        return this.chat.exportConversation(this.state.getState().currentConversationId, format);
    }

    /**
     * Search conversations
     */
    searchConversations(query) {
        return this.chat.searchConversations(query);
    }

    /**
     * Reset assistant
     */
    reset() {
        this.state.reset();
        this.ui.clearMessages();
        this.images.clearImages();
        this.keyboard.reset();
        this.storage.clearLocal();
        console.log('[AdminAssistant] Reset');
        this.emitter.emit('assistant:reset');
    }

    /**
     * Destroy assistant
     */
    destroy() {
        this.keyboard.disable();
        this.ui.clearMessages();
        this.emitter = null;
        console.log('[AdminAssistant] Destroyed');
    }
}

export default AdminAssistant;
