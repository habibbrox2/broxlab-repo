import Anthropic from '@anthropic-ai/sdk';
import { Message, ChatOptions, ResponseMeta } from '../types/index';
import { BaseAIProvider } from './base.provider';
import logger from '../utils/logger';

export class AnthropicProvider extends BaseAIProvider {
    constructor(apiKey: string, model: string = 'claude-3.0') {
        super({ apiKey, model, name: 'anthropic', useClient: false });
        this.client = new Anthropic({ apiKey: apiKey || '' });
    }

    private normalizeContent(content: string | any[]): string {
        if (typeof content === 'string') {
            return content;
        }

        return content
            .map((part) => {
                if (part?.type === 'text' && typeof part.text === 'string') {
                    return part.text;
                }
                if (part?.type === 'image_url' && part.image_url?.url) {
                    return `[Image] ${part.image_url.url}`;
                }
                return '';
            })
            .filter(Boolean)
            .join(' ');
    }

    private buildAnthropicMessages(messages: Message[]) {
        return messages.map((message) => ({
            role: message.role === 'assistant' ? 'assistant' : 'user',
            content: this.normalizeContent(message.content),
        }));
    }

    async chat(
        systemPrompt: string,
        messages: Message[],
        options?: ChatOptions
    ): Promise<{ content: string; meta: ResponseMeta }> {
        const startTime = Date.now();
        const systemMessage = messages.find((message) => message.role === 'system');
        const chatMessages = this.buildAnthropicMessages(messages.filter((message) => message.role !== 'system'));

        try {
            const response = await this.client.messages.create({
                model: options?.model || this.config.model || 'claude-3.0',
                max_tokens: options?.maxTokens || 4096,
                temperature: options?.temperature,
                top_p: options?.topP,
                system: systemMessage?.content as string | undefined,
                messages: chatMessages as any,
            });

            const content = response.content?.[0]?.type === 'text' ? response.content[0].text : '';
            const meta: ResponseMeta = {
                model: response.model,
                provider: 'anthropic',
                tokensUsed: (response.usage?.input_tokens || 0) + (response.usage?.output_tokens || 0),
                executionTimeMs: Date.now() - startTime,
            };

            logger.debug('Anthropic chat response:', {
                model: meta.model,
                tokensUsed: meta.tokensUsed,
                executionTimeMs: meta.executionTimeMs,
            });

            return { content, meta };
        } catch (error) {
            logger.error('Anthropic chat error:', error);
            throw error;
        }
    }

    async *streamChat(
        systemPrompt: string,
        messages: Message[],
        options?: ChatOptions
    ): AsyncGenerator<{ content: string; meta?: ResponseMeta }> {
        const startTime = Date.now();
        const systemMessage = messages.find((message) => message.role === 'system');
        const chatMessages = this.buildAnthropicMessages(messages.filter((message) => message.role !== 'system'));

        try {
            const stream = await this.client.messages.create({
                model: options?.model || this.config.model || 'claude-3.0',
                max_tokens: options?.maxTokens || 4096,
                temperature: options?.temperature,
                top_p: options?.topP,
                system: systemMessage?.content as string | undefined,
                messages: chatMessages as any,
                stream: true,
            });

            for await (const chunk of stream) {
                if (chunk.type === 'content_block_delta' && chunk.delta?.type === 'text_delta') {
                    yield { content: chunk.delta.text };
                }
            }

            yield {
                content: '',
                meta: {
                    model: options?.model || this.config.model || 'claude-3.0',
                    provider: 'anthropic',
                    executionTimeMs: Date.now() - startTime,
                },
            };
        } catch (error) {
            logger.error('Anthropic stream error:', error);
            throw error;
        }
    }
}
