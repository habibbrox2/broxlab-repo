/**
 * Base Provider Abstract Class
 * 
 * All AI providers must extend this class and implement:
 * - chat()
 * - chatStream()
 * - embed()
 */

export class BaseProvider {
    constructor(config) {
        this.name = config.name;
        this.apiKey = config.apiKey;
        this.baseUrl = config.baseUrl;
        this.defaultModel = config.defaultModel;
        this.models = config.models || [];
        this.supportsStreaming = config.supportsStreaming || false;
        this.supportsVision = config.supportsVision || false;
        this.maxTokens = config.maxTokens || 4096;
    }

    /**
     * Send a chat request (must be implemented by subclass)
     */
    async chat(messages, model, options = {}) {
        throw new Error('chat() must be implemented by subclass');
    }

    /**
     * Stream chat response (must be implemented by subclass)
     */
    async *chatStream(messages, model, options = {}) {
        throw new Error('chatStream() must be implemented by subclass');
    }

    /**
     * Generate embeddings (optional - not all providers support it)
     */
    async embed(text, model) {
        throw new Error('Embeddings not supported by this provider');
    }

    /**
     * Validate API key is present
     */
    validateApiKey() {
        if (!this.apiKey) {
            throw new Error(`${this.name}: API key is required`);
        }
    }

    /**
     * Validate model is supported
     */
    validateModel(model) {
        if (this.models.length > 0 && !this.models.includes(model)) {
            throw new Error(`${this.name}: Model "${model}" not supported. Available: ${this.models.join(', ')}`);
        }
    }

    /**
     * Build standard request options
     */
    buildRequestOptions(messages, options = {}) {
        return {
            temperature: options.temperature ?? 0.7,
            maxTokens: options.maxTokens ?? this.maxTokens,
            topP: options.topP ?? 1,
            stop: options.stop ?? null,
            ...options,
        };
    }

    /**
     * Parse streaming chunk (to be overridden by subclasses)
     */
    parseStreamChunk(chunk) {
        return chunk;
    }

    /**
     * Parse response (to be overridden by subclasses)
     */
    parseResponse(data) {
        return data;
    }
}

/**
 * Standard response format for all providers
 */
export class AIResponse {
    constructor(data) {
        this.content = data.content || '';
        this.model = data.model || '';
        this.provider = data.provider || '';
        this.inputTokens = data.inputTokens || 0;
        this.outputTokens = data.outputTokens || 0;
        this.finishReason = data.finishReason || 'stop';
        this.raw = data.raw || null;
    }

    toJSON() {
        return {
            content: this.content,
            model: this.model,
            provider: this.provider,
            usage: {
                input: this.inputTokens,
                output: this.outputTokens,
                total: this.inputTokens + this.outputTokens,
            },
            finishReason: this.finishReason,
        };
    }
}

/**
 * Streaming chunk format
 */
export class StreamChunk {
    constructor(data) {
        this.content = data.content || '';
        this.delta = data.delta || '';
        this.done = data.done || false;
    }

    toJSON() {
        return {
            content: this.content,
            delta: this.delta,
            done: this.done,
        };
    }
}

export default BaseProvider;