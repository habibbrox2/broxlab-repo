import { Message, ChatOptions, ResponseMeta } from '../types/index';
import { BaseAIProvider } from './base.provider';
import logger from '../utils/logger';

export class KiloProvider extends BaseAIProvider {
    constructor(apiKey: string, model: string = 'gpt-3.5-turbo') {
        super({ apiKey, model, name: 'kilo', baseURL: 'https://api.kilo.ai/api/gateway' });
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

            logger.debug('Kilo chat request:', {
                model: requestOptions.model,
                messageCount: messages.length,
            });

            const response = await this.client.chat.completions.create(requestOptions);
            const content = response.choices[0]?.message?.content || '';
            const meta = this.extractMeta(response);
            meta.executionTimeMs = Date.now() - startTime;

            logger.debug('Kilo chat response:', {
                model: meta.model,
                tokensUsed: meta.tokensUsed,
                executionTimeMs: meta.executionTimeMs,
            });

            return { content, meta };
        } catch (error) {
            logger.error('Kilo chat error:', error);
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

            logger.debug('Kilo stream request:', {
                model: requestOptions.model,
                messageCount: messages.length,
            });

            const stream = await this.client.chat.completions.create(requestOptions);
            let model = '';

            for await (const chunk of stream) {
                const delta = chunk.choices[0]?.delta;
                if (delta?.content) {
                    yield { content: delta.content };
                }

                if (chunk.model) {
                    model = chunk.model;
                }

                if (chunk.choices[0]?.finish_reason) {
                    const meta: ResponseMeta = {
                        model,
                        provider: 'kilo',
                        finishReason: chunk.choices[0].finish_reason,
                        executionTimeMs: Date.now() - startTime,
                    };

                    yield { content: '', meta };
                }
            }
        } catch (error) {
            logger.error('Kilo stream error:', error);
            throw error;
        }
    }
}
