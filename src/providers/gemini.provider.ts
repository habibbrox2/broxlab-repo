import { GoogleGenerativeAI, GenerativeModel } from '@google/generative-ai';
import { Message, ChatOptions, ResponseMeta } from '../types/index';
import { BaseAIProvider } from './base.provider';
import logger from '../utils/logger';

export class GeminiProvider extends BaseAIProvider {
    private modelClient: GenerativeModel;

    constructor(apiKey: string, model: string = 'gemini-1.5-flash') {
        super({ apiKey, model, name: 'google', useClient: false });
        const genAI = new GoogleGenerativeAI(apiKey || '');
        this.modelClient = genAI.getGenerativeModel({ model: this.config.model || model });
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

    private buildPrompt(messages: Message[]): string {
        const systemMessage = messages.find((message) => message.role === 'system');
        const userMessages = messages.filter((message) => message.role !== 'system');

        let prompt = '';
        if (systemMessage) {
            prompt += `${this.normalizeContent(systemMessage.content)}\n\n`;
        }

        prompt += userMessages.map((message) => this.normalizeContent(message.content)).join('\n');
        return prompt;
    }

    async chat(
        _systemPrompt: string,
        messages: Message[],
        options?: ChatOptions
    ): Promise<{ content: string; meta: ResponseMeta }> {
        const startTime = Date.now();
        const prompt = this.buildPrompt(messages);

        try {
            const result = await this.modelClient.generateContent(prompt);
            const response = result.response;
            const text = response.text();

            const meta: ResponseMeta = {
                model: this.config.model || 'gemini-pro',
                provider: 'google',
                executionTimeMs: Date.now() - startTime,
            };

            logger.debug('Gemini chat response:', {
                model: meta.model,
                executionTimeMs: meta.executionTimeMs,
            });

            return { content: text, meta };
        } catch (error) {
            logger.error('Gemini chat error:', error);
            throw error;
        }
    }

    async *streamChat(
        _systemPrompt: string,
        messages: Message[],
        _options?: ChatOptions
    ): AsyncGenerator<{ content: string; meta?: ResponseMeta }> {
        const startTime = Date.now();
        const prompt = this.buildPrompt(messages);

        try {
            const result = await this.modelClient.generateContentStream(prompt);

            for await (const chunk of result.stream) {
                const text = chunk.text?.();
                if (text) {
                    yield { content: text };
                }
            }

            yield {
                content: '',
                meta: {
                    model: this.config.model || 'gemini-pro',
                    provider: 'google',
                    executionTimeMs: Date.now() - startTime,
                },
            };
        } catch (error) {
            logger.error('Gemini stream error:', error);
            throw error;
        }
    }
}
