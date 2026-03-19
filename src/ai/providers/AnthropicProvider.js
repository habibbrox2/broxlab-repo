/**
 * Anthropic Provider
 * 
 * Implementation using @anthropic-ai/sdk
 * Supports Claude 3.5, Claude 3 Opus, Claude 3 Sonnet, Claude 3 Haiku
 */

import Anthropic from '@anthropic-ai/sdk';
import { BaseProvider, AIResponse, StreamChunk } from './BaseProvider.js';
import logger from '../utils/Logger.js';

export class AnthropicProvider extends BaseProvider {
    constructor(config) {
        super(config);
        this.client = null;
    }

    /**
     * Initialize the Anthropic client
     */
    initialize() {
        if (!this.apiKey) {
            throw new Error('Anthropic API key is required');
        }

        if (!this.client) {
            this.client = new Anthropic({
                apiKey: this.apiKey,
                baseURL: this.baseUrl !== 'https://api.anthropic.com/v1'
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

        // Extract system message for Anthropic
        const { system, chatMessages } = this.extractSystemMessage(messages);

        const requestOptions = {
            model: modelName,
            messages: chatMessages,
            system: system || undefined,
            temperature: options.temperature ?? 0.7,
            max_tokens: options.maxTokens ?? this.maxTokens,
            top_p: options.topP ?? 1,
            top_k: options.topK ?? 1,
            stop_sequences: options.stop ?? [],
        };

        // Add tools if provided
        if (options.tools) {
            requestOptions.tools = options.tools;
        }

        try {
            const startTime = Date.now();
            const response = await this.client.messages.create(requestOptions);
            const latency = Date.now() - startTime;

            const content = this.extractContent(response.content);

            logger.logResponse(this.name, modelName, latency, {
                input: response.usage?.input_tokens || 0,
                output: response.usage?.output_tokens || 0,
            });

            return new AIResponse({
                content,
                model: modelName,
                provider: 'anthropic',
                inputTokens: response.usage?.input_tokens || 0,
                outputTokens: response.usage?.output_tokens || 0,
                finishReason: response.stop_reason || 'end_turn',
                raw: response,
            });
        } catch (error) {
            logger.logError(error, { provider: 'anthropic', model: modelName });
            throw error;
        }
    }

    /**
     * Stream chat response
     */
    async *chatStream(messages, model, options = {}) {
        this.validateApiKey();

        const modelName = model || this.defaultModel;

        const { system, chatMessages } = this.extractSystemMessage(messages);

        const requestOptions = {
            model: modelName,
            messages: chatMessages,
            system: system || undefined,
            temperature: options.temperature ?? 0.7,
            max_tokens: options.maxTokens ?? this.maxTokens,
            top_p: options.topP ?? 1,
            top_k: options.topK ?? 1,
            stop_sequences: options.stop ?? [],
            stream: true,
        };

        try {
            const stream = await this.client.messages.stream(requestOptions);

            for await (const chunk of stream) {
                if (chunk.type === 'content_block_delta') {
                    const text = chunk.delta?.text || '';

                    yield new StreamChunk({
                        content: text,
                        delta: text,
                        done: false,
                    });
                } else if (chunk.type === 'message_stop') {
                    yield new StreamChunk({ done: true });
                }
            }
        } catch (error) {
            logger.logError(error, { provider: 'anthropic', model: modelName, streaming: true });
            throw error;
        }
    }

    /**
     * Extract system message from messages array
     */
    extractSystemMessage(messages) {
        let system = '';
        const chatMessages = [];

        for (const msg of messages) {
            if (msg.role === 'system') {
                system = msg.content;
            } else {
                chatMessages.push({
                    role: msg.role === 'assistant' ? 'assistant' : 'user',
                    content: this.convertContent(msg.content),
                });
            }
        }

        return { system, chatMessages };
    }

    /**
     * Convert content to Anthropic format
     */
    convertContent(content) {
        if (typeof content === 'string') {
            return content;
        }

        if (Array.isArray(content)) {
            return content.map(part => {
                if (part.type === 'text') {
                    return {
                        type: 'text',
                        text: part.text,
                    };
                } else if (part.type === 'image_url') {
                    const url = part.image_url?.url || '';

                    // Handle base64 images
                    if (url.startsWith('data:')) {
                        const [mimeType, base64Data] = url.split(',');
                        const mediaType = mimeType.replace('data:', '').replace(';base64', '');
                        return {
                            type: 'image',
                            source: {
                                type: 'base64',
                                media_type: mediaType,
                                data: base64Data,
                            },
                        };
                    }

                    return {
                        type: 'image',
                        source: {
                            type: 'url',
                            url: url,
                        },
                    };
                }
                return null;
            }).filter(Boolean);
        }

        return content;
    }

    /**
     * Extract text content from Anthropic response
     */
    extractContent(contentBlocks) {
        if (!contentBlocks) return '';

        if (typeof contentBlocks === 'string') {
            return contentBlocks;
        }

        return contentBlocks
            .filter(block => block.type === 'text')
            .map(block => block.text)
            .join('');
    }
}

export default AnthropicProvider;