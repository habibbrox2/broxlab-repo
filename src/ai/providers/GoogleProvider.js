/**
 * Google AI Provider
 * 
 * Implementation using @google/generative-ai SDK
 * Supports Gemini models
 */

import { GoogleGenerativeAI } from '@google/generative-ai';
import { BaseProvider, AIResponse, StreamChunk } from './BaseProvider.js';
import logger from '../utils/Logger.js';

export class GoogleProvider extends BaseProvider {
    constructor(config) {
        super(config);
        this.client = null;
    }

    /**
     * Initialize the Google AI client
     */
    initialize() {
        if (!this.apiKey) {
            throw new Error('Google AI API key is required');
        }

        if (!this.client) {
            this.client = new GoogleGenerativeAI(this.apiKey);
        }

        return this;
    }

    /**
     * Send a chat request
     */
    async chat(messages, model, options = {}) {
        this.validateApiKey();

        const modelName = model || this.defaultModel;
        const genModel = this.client.getGenerativeModel({
            model: modelName,
        });

        // Convert messages to Google format
        const contents = this.convertMessages(messages);

        // Extract system instruction if present
        let systemInstruction = null;
        const systemMsg = messages.find(m => m.role === 'system');
        if (systemMsg) {
            systemInstruction = { text: systemMsg.content };
        }

        const generationConfig = {
            temperature: options.temperature ?? 0.7,
            maxOutputTokens: options.maxTokens ?? this.maxTokens,
            topP: options.topP ?? 0.95,
            topK: options.topK ?? 40,
            stopSequences: options.stop ?? [],
        };

        const request = {
            contents,
            systemInstruction,
            generationConfig,
        };

        try {
            const startTime = Date.now();
            const result = await genModel.generateContent(request);
            const latency = Date.now() - startTime;

            const response = result.response;
            const text = response.text();

            logger.logResponse(this.name, modelName, latency, {
                input: result.usageMetadata?.promptTokenCount || 0,
                output: result.usageMetadata?.candidatesTokenCount || 0,
            });

            return new AIResponse({
                content: text,
                model: modelName,
                provider: 'google',
                inputTokens: result.usageMetadata?.promptTokenCount || 0,
                outputTokens: result.usageMetadata?.candidatesTokenCount || 0,
                finishReason: response.candidates?.[0]?.finishReason || 'stop',
                raw: result,
            });
        } catch (error) {
            logger.logError(error, { provider: 'google', model: modelName });
            throw error;
        }
    }

    /**
     * Stream chat response
     */
    async *chatStream(messages, model, options = {}) {
        this.validateApiKey();

        const modelName = model || this.defaultModel;
        const genModel = this.client.getGenerativeModel({
            model: modelName,
        });

        const contents = this.convertMessages(messages);

        let systemInstruction = null;
        const systemMsg = messages.find(m => m.role === 'system');
        if (systemMsg) {
            systemInstruction = { text: systemMsg.content };
        }

        const generationConfig = {
            temperature: options.temperature ?? 0.7,
            maxOutputTokens: options.maxTokens ?? this.maxTokens,
            topP: options.topP ?? 0.95,
            topK: options.topK ?? 40,
        };

        const request = {
            contents,
            systemInstruction,
            generationConfig,
        };

        try {
            const result = await genModel.generateContentStream(request);

            for await (const chunk of result.stream) {
                const text = chunk.text();
                yield new StreamChunk({
                    content: text,
                    delta: text,
                    done: false,
                });
            }

            yield new StreamChunk({ done: true });
        } catch (error) {
            logger.logError(error, { provider: 'google', model: modelName, streaming: true });
            throw error;
        }
    }

    /**
     * Convert messages to Google format
     */
    convertMessages(messages) {
        const roleMap = {
            'user': 'user',
            'assistant': 'model',
            'system': 'user', // Google doesn't have system role, include in first user msg
        };

        const contents = [];

        for (const msg of messages) {
            if (msg.role === 'system') continue; // Handled separately

            const role = roleMap[msg.role] || 'user';

            // Handle multimodal content
            let parts;
            if (typeof msg.content === 'string') {
                parts = [{ text: msg.content }];
            } else if (Array.isArray(msg.content)) {
                parts = msg.content.map(part => {
                    if (part.type === 'text') {
                        return { text: part.text };
                    } else if (part.type === 'image_url') {
                        return {
                            inlineData: {
                                mimeType: part.image_url?.mime || 'image/jpeg',
                                data: this.extractBase64(part.image_url?.url),
                            }
                        };
                    }
                    return null;
                }).filter(Boolean);
            }

            contents.push({
                role,
                parts,
            });
        }

        return contents;
    }

    /**
     * Extract base64 data from data URL
     */
    extractBase64(url) {
        if (!url) return null;

        // Handle data URLs
        if (url.startsWith('data:')) {
            const parts = url.split(',');
            return parts[1] || null;
        }

        // For regular URLs, we'd need to fetch - return as-is for now
        return url;
    }
}

export default GoogleProvider;