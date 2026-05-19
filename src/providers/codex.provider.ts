import { Message, ChatOptions, ResponseMeta } from '../types/index';
import { BaseAIProvider } from './base.provider';
import logger from '../utils/logger';

interface CodeCompletionOptions extends ChatOptions {
    prompt?: string;
    language?: string;
    maxTokens?: number;
    temperature?: number;
    topP?: number;
    frequencyPenalty?: number;
    presencePenalty?: number;
    stop?: string[];
}

/**
 * OpenAI Codex Provider
 * Specialized for code completion tasks using OpenAI's Codex model
 * Endpoint: https://api.openai.com/v1/code_completions
 */
export class CodexProvider extends BaseAIProvider {
    constructor(apiKey: string, model: string = 'code-davinci-002') {
        super({ apiKey, model, name: 'codex', baseURL: 'https://api.openai.com/v1' });
    }

    /**
     * Complete code from a prompt
     * @param prompt The code prompt/context to complete
     * @param options Code completion options
     */
    async completeCode(
        prompt: string,
        options?: CodeCompletionOptions
    ): Promise<{ completion: string; meta: ResponseMeta }> {
        const startTime = Date.now();

        try {
            if (!prompt || prompt.trim().length === 0) {
                throw new Error('Prompt is required for code completion');
            }

            const requestData = {
                model: this.modelName,
                prompt: prompt,
                max_tokens: options?.maxTokens || 256,
                temperature: options?.temperature ?? 0.7,
                top_p: options?.topP ?? 1,
                frequency_penalty: options?.frequencyPenalty ?? 0,
                presence_penalty: options?.presencePenalty ?? 0,
                stop: options?.stop || ['\n\nclass', '\ndef ', '\n\n#'],
            };

            logger.debug('Codex code completion request:', {
                model: this.modelName,
                promptLength: prompt.length,
            });

            const response = await (this.client.code?.completions?.create
                ? this.client.code.completions.create(requestData)
                : this.client.request?.({
                    method: 'POST',
                    url: '/v1/code_completions',
                    data: requestData,
                })
            );

            const completion = response?.choices?.[0]?.text || '';
            const meta: ResponseMeta = {
                model: response?.model || this.modelName,
                provider: 'codex',
                finishReason: response?.choices?.[0]?.finish_reason || 'stop',
                tokensUsed: response?.usage?.total_tokens || 0,
                inputTokens: response?.usage?.prompt_tokens || 0,
                outputTokens: response?.usage?.completion_tokens || 0,
                executionTimeMs: Date.now() - startTime,
            };

            logger.debug('Codex code completion response:', {
                model: meta.model,
                tokensUsed: meta.tokensUsed,
                completionLength: completion.length,
                executionTimeMs: meta.executionTimeMs,
            });

            return { completion, meta };
        } catch (error) {
            logger.error('Codex code completion error:', error);
            throw error;
        }
    }

    /**
     * Chat interface (for compatibility with chat-based code generation)
     * Converts chat format to code completion format
     */
    async chat(
        systemPrompt: string,
        messages: Message[],
        options?: ChatOptions
    ): Promise<{ content: string; meta: ResponseMeta }> {
        // Convert chat messages to a prompt for code completion
        let prompt = systemPrompt + '\n\n';

        // Build prompt from messages
        for (const msg of messages) {
            if (msg.role === 'user') {
                prompt += `# ${msg.content}\n`;
            } else if (msg.role === 'assistant') {
                prompt += msg.content + '\n';
            }
        }

        const result = await this.completeCode(prompt, options);
        return { content: result.completion, meta: result.meta };
    }

    /**
     * Stream code completion (limited support - Codex may not support streaming)
     */
    async *streamChat(
        systemPrompt: string,
        messages: Message[],
        options?: ChatOptions
    ): AsyncGenerator<{ content: string; meta?: ResponseMeta }> {
        const result = await this.chat(systemPrompt, messages, options);
        yield { content: result.content, meta: result.meta };
    }
}
