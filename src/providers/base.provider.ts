import OpenAI from 'openai';
import { Message, ChatOptions, ResponseMeta } from '../types/index';

export abstract class BaseAIProvider {
    protected client: OpenAI;
    protected modelName: string;
    protected providerName: string;

    constructor(config: { apiKey: string; baseURL: string; model: string; name: string }) {
        this.client = new OpenAI({
            apiKey: config.apiKey,
            baseURL: config.baseURL,
        });
        this.modelName = config.model;
        this.providerName = config.name;
    }

    /**
     * Execute chat request (non-streaming)
     */
    abstract chat(
        systemPrompt: string,
        messages: Message[],
        options?: ChatOptions
    ): Promise<{ content: string; meta: ResponseMeta }>;

    /**
     * Execute chat request with streaming
     */
    abstract streamChat(
        systemPrompt: string,
        messages: Message[],
        options?: ChatOptions
    ): AsyncGenerator<{ content: string; meta?: ResponseMeta }>;

    /**
     * Build messages array for OpenAI API
     */
    protected buildMessages(
        systemPrompt: string,
        messages: Message[]
    ): OpenAI.Chat.ChatCompletionMessageParam[] {
        const result: OpenAI.Chat.ChatCompletionMessageParam[] = [
            { role: 'system', content: systemPrompt },
        ];

        for (const m of messages) {
            if (m.role === 'assistant') {
                result.push({
                    role: 'assistant',
                    content: m.content as string, // Assistant messages should always be strings
                });
            } else if (m.role === 'user') {
                result.push({
                    role: 'user',
                    content: m.content, // Can be string or vision content array
                });
            }
        }

        return result;
    }

    /**
     * Build chat options
     */
    protected buildOptions(
        options?: ChatOptions,
        messages?: OpenAI.Chat.ChatCompletionMessageParam[]
    ): OpenAI.ChatCompletionCreateParamsNonStreaming {
        return {
            messages: messages || [],
            model: options?.model || this.modelName,
            max_tokens: options?.maxTokens,
            temperature: options?.temperature,
            top_p: options?.topP,
            presence_penalty: options?.presencePenalty,
            frequency_penalty: options?.frequencyPenalty,
        };
    }

    /**
     * Build streaming chat options
     */
    protected buildStreamOptions(
        options?: ChatOptions,
        messages?: OpenAI.Chat.ChatCompletionMessageParam[]
    ): OpenAI.ChatCompletionCreateParamsStreaming {
        return {
            messages: messages || [],
            model: options?.model || this.modelName,
            max_tokens: options?.maxTokens,
            temperature: options?.temperature,
            top_p: options?.topP,
            presence_penalty: options?.presencePenalty,
            frequency_penalty: options?.frequencyPenalty,
            stream: true,
        };
    }

    /**
     * Extract response metadata
     */
    protected extractMeta(
        response: OpenAI.Chat.Completions.ChatCompletion
    ): ResponseMeta {
        return {
            model: response.model,
            provider: this.providerName,
            tokensUsed: response.usage?.total_tokens,
            finishReason: response.choices[0]?.finish_reason,
        };
    }

    /**
     * Get provider name
     */
    getName(): string {
        return this.providerName;
    }

    /**
     * Get model name
     */
    getModel(): string {
        return this.modelName;
    }
}
