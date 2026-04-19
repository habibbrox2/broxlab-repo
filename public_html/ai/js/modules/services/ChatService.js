/**
 * ChatService - API Communication Layer
 * Path: /public_html/ai/js/modules/services/ChatService.js
 *
 * Handles:
 *  - Message sending via SSE or REST
 *  - CSRF token refresh
 *  - Error recovery with exponential backoff
 *  - Request/response logging
 */

export class ChatService {
    constructor(config = {}) {
        this.config = {
            baseUrl: '/api/ai',
            timeout: 30000,
            maxRetries: 3,
            retryDelay: 1000,
            ...config,
        };

        this.csrfToken = null;
        this.lastRequestTime = 0;
        this.requestQueue = [];
        this.isOnline = navigator.onLine;

        this.setupNetworkListeners();
        this.refreshCsrfToken();
    }

    /**
     * Setup network change listeners
     */
    setupNetworkListeners() {
        window.addEventListener('online', () => {
            this.isOnline = true;
            console.log('[ChatService] Online');
            this.processQueue();
        });

        window.addEventListener('offline', () => {
            this.isOnline = false;
            console.log('[ChatService] Offline');
        });
    }

    /**
     * Refresh CSRF token
     */
    async refreshCsrfToken() {
        try {
            const response = await fetch('/api/csrf-token', {
                method: 'GET',
                credentials: 'include',
            });

            if (response.ok) {
                const data = await response.json();
                this.csrfToken = data.token;
                console.log('[ChatService] CSRF token refreshed');
            }
        } catch (e) {
            console.error('[ChatService] Failed to refresh CSRF token:', e);
        }
    }

    /**
     * Get current CSRF token
     */
    getCsrfToken() {
        // Try to get from meta tag
        if (!this.csrfToken) {
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) {
                this.csrfToken = meta.getAttribute('content');
            }
        }
        return this.csrfToken;
    }

    /**
     * Send message and get response (SSE streaming)
     */
    async sendMessageStream(payload, onChunk, onComplete, onError) {
        if (!this.isOnline) {
            this.requestQueue.push({ type: 'message', payload, onChunk, onComplete, onError });
            throw new Error('Offline - message queued');
        }

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), this.config.timeout);

        try {
            // Refresh CSRF token
            await this.refreshCsrfToken();

            const response = await fetch(`${this.config.baseUrl}/chat/stream`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCsrfToken() || '',
                },
                body: JSON.stringify(payload),
                signal: controller.signal,
                credentials: 'include',
            });

            clearTimeout(timeoutId);

            if (!response.ok) {
                throw new Error(`API error: ${response.status} ${response.statusText}`);
            }

            // Handle SSE stream
            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });

                // Process complete lines
                const lines = buffer.split('\n');
                buffer = lines.pop() || '';

                for (const line of lines) {
                    if (line.startsWith('data: ')) {
                        try {
                            const data = JSON.parse(line.substring(6));
                            onChunk(data);
                        } catch (e) {
                            console.error('[ChatService] Failed to parse SSE chunk:', e);
                        }
                    }
                }
            }

            onComplete();
            console.log('[ChatService] Stream completed');
        } catch (e) {
            if (e.name === 'AbortError') {
                onError(new Error('Request timeout'));
            } else {
                onError(e);
            }
            console.error('[ChatService] Stream error:', e);
        }
    }

    /**
     * Send message (REST with retry logic)
     */
    async sendMessage(payload, retryCount = 0) {
        if (!this.isOnline) {
            this.requestQueue.push({ type: 'message', payload });
            throw new Error('Offline - message queued');
        }

        try {
            // Refresh CSRF token
            await this.refreshCsrfToken();

            const response = await fetch(`${this.config.baseUrl}/chat`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCsrfToken() || '',
                },
                body: JSON.stringify(payload),
                credentials: 'include',
                signal: AbortSignal.timeout(this.config.timeout),
            });

            if (!response.ok) {
                if (response.status === 429 && retryCount < this.config.maxRetries) {
                    // Rate limited - retry with exponential backoff
                    const delay = this.config.retryDelay * Math.pow(2, retryCount);
                    await new Promise((resolve) => setTimeout(resolve, delay));
                    return this.sendMessage(payload, retryCount + 1);
                }
                throw new Error(`API error: ${response.status} ${response.statusText}`);
            }

            const data = await response.json();
            console.log('[ChatService] Message sent successfully');
            return data;
        } catch (e) {
            if (retryCount < this.config.maxRetries && this.isRecoverable(e)) {
                const delay = this.config.retryDelay * Math.pow(2, retryCount);
                await new Promise((resolve) => setTimeout(resolve, delay));
                return this.sendMessage(payload, retryCount + 1);
            }
            throw e;
        }
    }

    /**
     * Export conversation
     */
    async exportConversation(conversationId, format = 'json') {
        try {
            await this.refreshCsrfToken();

            const response = await fetch(`${this.config.baseUrl}/export`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCsrfToken() || '',
                },
                body: JSON.stringify({ conversationId, format }),
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Export failed: ${response.status}`);
            }

            // Handle different response types
            if (format === 'json') {
                return await response.json();
            } else if (format === 'pdf' || format === 'markdown') {
                return await response.blob();
            }

            return response;
        } catch (e) {
            console.error('[ChatService] Export failed:', e);
            throw e;
        }
    }

    /**
     * Search conversations
     */
    async searchConversations(query, limit = 20) {
        try {
            const params = new URLSearchParams({ q: query, limit });
            const response = await fetch(`${this.config.baseUrl}/search?${params}`, {
                method: 'GET',
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Search failed: ${response.status}`);
            }

            return await response.json();
        } catch (e) {
            console.error('[ChatService] Search failed:', e);
            throw e;
        }
    }

    /**
     * Tag conversation
     */
    async tagConversation(conversationId, tags) {
        try {
            await this.refreshCsrfToken();

            const response = await fetch(`${this.config.baseUrl}/tag`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCsrfToken() || '',
                },
                body: JSON.stringify({ conversationId, tags }),
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Tag failed: ${response.status}`);
            }

            return await response.json();
        } catch (e) {
            console.error('[ChatService] Tag failed:', e);
            throw e;
        }
    }

    /**
     * Execute command
     */
    async executeCommand(commandName, params = {}) {
        try {
            await this.refreshCsrfToken();

            const response = await fetch(`${this.config.baseUrl}/command/${commandName}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCsrfToken() || '',
                },
                body: JSON.stringify(params),
                credentials: 'include',
                signal: AbortSignal.timeout(this.config.timeout),
            });

            if (!response.ok) {
                throw new Error(`Command failed: ${response.status}`);
            }

            return await response.json();
        } catch (e) {
            console.error('[ChatService] Command execution failed:', e);
            throw e;
        }
    }

    /**
     * Check if error is recoverable (network vs client error)
     */
    isRecoverable(error) {
        if (error instanceof TypeError) {
            return error.message.includes('fetch');
        }
        if (error instanceof DOMException && error.name === 'AbortError') {
            return true;
        }
        return false;
    }

    /**
     * Process queued requests when back online
     */
    async processQueue() {
        while (this.requestQueue.length > 0) {
            const request = this.requestQueue.shift();
            try {
                if (request.type === 'message') {
                    await this.sendMessage(request.payload);
                    console.log('[ChatService] Queued message processed');
                }
            } catch (e) {
                console.error('[ChatService] Failed to process queued request:', e);
                // Re-queue if still fails
                this.requestQueue.unshift(request);
                break;
            }
        }
    }

    /**
     * Get request queue length
     */
    getQueueLength() {
        return this.requestQueue.length;
    }

    /**
     * Clear request queue
     */
    clearQueue() {
        this.requestQueue = [];
        console.log('[ChatService] Request queue cleared');
    }
}

export default ChatService;
