import OpenAI from 'openai';
import { BaseAIProvider, AIMessage, AIResponse, AIOptions, AIProviderConfig, AIStreamChunk } from '../types';

export class OpenAIProvider extends BaseAIProvider {
  private client: OpenAI;

  constructor(config: AIProviderConfig) {
    super(config);
    this.client = new OpenAI({
      apiKey: config.apiKey,
      baseURL: config.baseURL,
    });
  }

  async generate(messages: AIMessage[], options: AIOptions = {}): Promise<AIResponse> {
    const openaiMessages = this.buildMessages(messages);

    const response = await this.client.chat.completions.create({
      messages: openaiMessages,
      model: options.model || this.config.model || 'gpt-4o',
      max_tokens: options.maxTokens,
      temperature: options.temperature,
      top_p: options.topP,
      presence_penalty: options.presencePenalty,
      frequency_penalty: options.frequencyPenalty,
    });

    return {
      content: response.choices[0]?.message.content || '',
      meta: {
        model: response.model,
        provider: this.getProviderName(),
        tokensUsed: response.usage?.total_tokens,
        finishReason: response.choices[0]?.finish_reason,
      },
    };
  }

  async *generateStream(messages: AIMessage[], options: AIOptions = {}): AsyncGenerator<AIStreamChunk> {
    const openaiMessages = this.buildMessages(messages);

    const stream = await this.client.chat.completions.create({
      messages: openaiMessages,
      model: options.model || this.config.model || 'gpt-4o',
      max_tokens: options.maxTokens,
      temperature: options.temperature,
      top_p: options.topP,
      presence_penalty: options.presencePenalty,
      frequency_penalty: options.frequencyPenalty,
      stream: true,
    });

    for await (const chunk of stream) {
      const content = chunk.choices[0]?.delta?.content || '';
      if (content) {
        yield {
          content,
          done: false,
        };
      }
    }

    yield {
      content: '',
      done: true,
      meta: {
        model: options.model || this.config.model || 'gpt-4o',
        provider: this.getProviderName(),
      },
    };
  }

  private buildMessages(messages: AIMessage[]): OpenAI.Chat.ChatCompletionMessageParam[] {
    return messages.map((m) => ({
      role: m.role,
      content: m.content,
    }));
  }
}