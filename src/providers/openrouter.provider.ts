import { Message, ChatOptions, ResponseMeta } from '../types/index.js';
import { BaseAIProvider } from './base.provider.js';
import logger from '../utils/logger.js';

export class OpenRouterProvider extends BaseAIProvider {
    constructor(apiKey: string, model: string = 'openrouter/auto') {
        super({
            apiKey,
            baseURL: 'https://openrouter.ai/api/v1',
            model,
            name: 'openrouter',
        });
    }

    async chat(
        systemPrompt: string,
        messages: Message[],
        options?: ChatOptions
    ): Promise<{ content: string; meta: ResponseMeta }> {
        const startTime = Date.now();

        try {
            const builtMessages = this.buildMessages(systemPrompt, messages);
            const requestOptions = this.buildOptions(options, builtMessages);

            logger.debug('OpenRouter chat request:', {
                model: requestOptions.model,
                messageCount: messages.length,
            });

            const response = await this.client.chat.completions.create(requestOptions);

            const content = response.choices[0]?.message?.content || '';
            const meta = this.extractMeta(response);
            meta.executionTimeMs = Date.now() - startTime;

            logger.debug('OpenRouter chat response:', {
                model: meta.model,
                tokensUsed: meta.tokensUsed,
                executionTimeMs: meta.executionTimeMs,
            });

            return { content, meta };
        } catch (error) {
            logger.error('OpenRouter chat error:', error);
            throw error;
        }
    }

    async *streamChat(
        systemPrompt: string,
        messages: Message[],
        options?: ChatOptions
    ): AsyncGenerator<{ content: string; meta?: ResponseMeta }> {
        const startTime = Date.now();

        try {
            const builtMessages = this.buildMessages(systemPrompt, messages);
            const requestOptions = this.buildStreamOptions(options, builtMessages);

            logger.debug('OpenRouter stream request:', {
                model: requestOptions.model,
                messageCount: messages.length,
            });

            const stream = await this.client.chat.completions.create(requestOptions);

            let fullContent = '';
            let model = '';

            for await (const chunk of stream) {
                const delta = chunk.choices[0]?.delta;

                if (delta?.content) {
                    fullContent += delta.content;
                    yield { content: delta.content };
                }

                if (chunk.model) {
                    model = chunk.model;
                }

                if (chunk.choices[0]?.finish_reason) {
                    const meta: ResponseMeta = {
                        model,
                        provider: 'openrouter',
                        finishReason: chunk.choices[0].finish_reason,
                        executionTimeMs: Date.now() - startTime,
                    };

                    logger.debug('OpenRouter stream complete:', {
                        model,
                        finishReason: meta.finishReason,
                        executionTimeMs: meta.executionTimeMs,
                    });

                    yield { content: '', meta };
                }
            }
        } catch (error) {
            logger.error('OpenRouter stream error:', error);
            throw error;
        }
    }
}
