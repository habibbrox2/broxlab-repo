/**
 * OpenAI-Compatible Provider
 * 
 * Generic provider for any OpenAI-compatible API:
 * - OpenRouter
 * - Fireworks AI
 * - Hugging Face
 * - Kilo.ai
 * - Custom providers
 */

import OpenAI from 'openai';
import { BaseProvider, AIResponse, StreamChunk } from './BaseProvider.js';
import logger from '../utils/Logger.js';

export class OpenAICompatibleProvider extends BaseProvider {
    constructor(config) {
        super(config);
        this.client = null;
        this.customHeaders = config.customHeaders || {};
    }

    /**
     * Initialize the OpenAI-compatible client
     */
    initialize() {
        if (!this.apiKey && this.name !== 'ollama') {
            throw new Error(`${this.name}: API key is required`);
        }

        if (!this.client) {
            this.client = new OpenAI({
                baseURL: this.baseUrl,
                apiKey: this.apiKey || 'ollama', // Ollama doesn't need key but OpenAI SDK requires one
                timeout: 60000,
                maxRetries: 3,
            });
        }

        return this;
    }

    /**
     * Send a chat request
     */
    async chat(messages, model, options = {}) {
        if (!this.apiKey && this.name !== 'ollama') {
            throw new Error(`${this.name}: API key is required`);
        }

        const modelName = model || this.defaultModel;

        const requestOptions = {
            model: modelName,
            messages: this.convertMessages(messages),
            temperature: options.temperature ?? 0.7,
            max_tokens: options.maxTokens ?? this.maxTokens,
            top_p: options.topP ?? 1,
            stop: options.stop ?? null,
            stream: false,
        };

        // Add tools if provided
        if (options.tools) {
            requestOptions.tools = options.tools;
            requestOptions.tool_choice = options.toolChoice || 'auto';
        }

        try {
            const startTime = Date.now();
            const response = await this.client.chat.completions.create(requestOptions);
            const latency = Date.now() - startTime;

            const choice = response.choices[0];
            const content = choice?.message?.content || '';

            logger.logResponse(this.name, modelName, latency, {
                input: response.usage?.prompt_tokens || 0,
                output: response.usage?.completion_tokens || 0,
            });

            return new AIResponse({
                content,
                model: modelName,
                provider: this.name,
                inputTokens: response.usage?.prompt_tokens || 0,
                outputTokens: response.usage?.completion_tokens || 0,
                finishReason: choice?.finish_reason || 'stop',
                raw: response,
            });
        } catch (error) {
            logger.logError(error, { provider: this.name, model: modelName });
            throw error;
        }
    }

    /**
     * Stream chat response
     */
    async *chatStream(messages, model, options = {}) {
        if (!this.apiKey && this.name !== 'ollama') {
            throw new Error(`${this.name}: API key is required`);
        }

        const modelName = model || this.defaultModel;

        const requestOptions = {
            model: modelName,
            messages: this.convertMessages(messages),
            temperature: options.temperature ?? 0.7,
            max_tokens: options.maxTokens ?? this.maxTokens,
            top_p: options.topP ?? 1,
            stop: options.stop ?? null,
            stream: true,
        };

        try {
            const stream = await this.client.chat.completions.create(requestOptions);

            for await (const chunk of stream) {
                const delta = chunk.choices[0]?.delta?.content || '';
                const done = chunk.choices[0]?.finish_reason !== null;

                yield new StreamChunk({
                    content: delta,
                    delta: delta,
                    done: done,
                });
            }
        } catch (error) {
            logger.logError(error, { provider: this.name, model: modelName, streaming: true });
            throw error;
        }
    }

    /**
     * Generate embeddings (if supported)
     */
    async embed(text, model) {
        if (!this.apiKey) {
            throw new Error(`${this.name}: API key is required`);
        }

        const embeddingModel = model || this.defaultModel;

        try {
            const response = await this.client.embeddings.create({
                model: embeddingModel,
                input: Array.isArray(text) ? text : [text],
            });

            return {
                embedding: response.data[0].embedding,
                model: embeddingModel,
                provider: this.name,
            };
        } catch (error) {
            logger.logError(error, { provider: this.name, operation: 'embed' });
            throw error;
        }
    }

    /**
     * Convert messages to OpenAI format
     */
    convertMessages(messages) {
        return messages.map(msg => ({
            role: msg.role === 'system' ? 'system' :
                msg.role === 'assistant' ? 'assistant' : 'user',
            content: typeof msg.content === 'string' ? msg.content : msg.content,
        }));
    }
}

export default OpenAICompatibleProvider;