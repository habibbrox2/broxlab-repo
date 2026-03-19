/**
 * OpenAI Provider
 * 
 * Implementation using openai SDK
 * Supports GPT-4o, GPT-4o-mini, GPT-4 Turbo, GPT-3.5 Turbo
 */

import OpenAI from 'openai';
import { BaseProvider, AIResponse, StreamChunk } from './BaseProvider.js';
import logger from '../utils/Logger.js';

export class OpenAIProvider extends BaseProvider {
    constructor(config) {
        super(config);
        this.client = null;
    }

    /**
     * Initialize the OpenAI client
     */
    initialize() {
        if (!this.apiKey) {
            throw new Error('OpenAI API key is required');
        }

        if (!this.client) {
            this.client = new OpenAI({
                apiKey: this.apiKey,
                baseURL: this.baseUrl !== 'https://api.openai.com/v1'
                    ? this.baseUrl
                    : undefined,
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
        this.validateApiKey();

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
                provider: 'openai',
                inputTokens: response.usage?.prompt_tokens || 0,
                outputTokens: response.usage?.completion_tokens || 0,
                finishReason: choice?.finish_reason || 'stop',
                raw: response,
            });
        } catch (error) {
            logger.logError(error, { provider: 'openai', model: modelName });
            throw error;
        }
    }

    /**
     * Stream chat response
     */
    async *chatStream(messages, model, options = {}) {
        this.validateApiKey();

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
                    delta,
                    done,
                });
            }
        } catch (error) {
            logger.logError(error, { provider: 'openai', model: modelName, streaming: true });
            throw error;
        }
    }

    /**
     * Generate embeddings
     */
    async embed(text, model) {
        this.validateApiKey();

        const embeddingModel = model || 'text-embedding-3-small';

        try {
            const response = await this.client.embeddings.create({
                model: embeddingModel,
                input: Array.isArray(text) ? text : [text],
            });

            return {
                embedding: response.data[0].embedding,
                model: embeddingModel,
                provider: 'openai',
            };
        } catch (error) {
            logger.logError(error, { provider: 'openai', operation: 'embed' });
            throw error;
        }
    }

    /**
     * Convert messages to OpenAI format
     */
    convertMessages(messages) {
        return messages.map(msg => {
            const role = msg.role === 'system' ? 'system' :
                msg.role === 'assistant' ? 'assistant' : 'user';

            // Handle multimodal content
            let content = msg.content;
            if (typeof msg.content === 'object' && !Array.isArray(msg.content)) {
                // Already structured content
                return { role, ...msg };
            }

            return { role, content };
        });
    }
}

export default OpenAIProvider;