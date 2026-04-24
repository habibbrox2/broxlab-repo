import Anthropic from '@anthropic-ai/sdk';
import { BaseAIProvider, AIMessage, AIResponse, AIOptions, AIProviderConfig, AIStreamChunk } from '../types';

export class AnthropicProvider extends BaseAIProvider {
  private client: Anthropic;

  constructor(config: AIProviderConfig) {
    super(config);
    this.client = new Anthropic({
      apiKey: config.apiKey,
    });
  }

  async generate(messages: AIMessage[], options: AIOptions = {}): Promise<AIResponse> {
    const systemMessage = messages.find(m => m.role === 'system');
    const chatMessages = messages.filter(m => m.role !== 'system');

    const response = await this.client.messages.create({
      model: options.model || this.config.model || 'claude-3-sonnet-20240229',
      max_tokens: options.maxTokens || 4096,
      temperature: options.temperature,
      top_p: options.topP,
      system: systemMessage?.content,
      messages: chatMessages.map(m => ({
        role: m.role === 'assistant' ? 'assistant' : 'user',
        content: m.content,
      })),
    });

    return {
      content: response.content[0]?.type === 'text' ? response.content[0].text : '',
      meta: {
        model: response.model,
        provider: this.getProviderName(),
        tokensUsed: response.usage?.input_tokens + response.usage?.output_tokens,
      },
    };
  }

  async *generateStream(messages: AIMessage[], options: AIOptions = {}): AsyncGenerator<AIStreamChunk> {
    const systemMessage = messages.find(m => m.role === 'system');
    const chatMessages = messages.filter(m => m.role !== 'system');

    const stream = await this.client.messages.create({
      model: options.model || this.config.model || 'claude-3-sonnet-20240229',
      max_tokens: options.maxTokens || 4096,
      temperature: options.temperature,
      top_p: options.topP,
      system: systemMessage?.content,
      messages: chatMessages.map(m => ({
        role: m.role === 'assistant' ? 'assistant' : 'user',
        content: m.content,
      })),
      stream: true,
    });

    for await (const chunk of stream) {
      if (chunk.type === 'content_block_delta' && chunk.delta.type === 'text_delta') {
        yield {
          content: chunk.delta.text,
          done: false,
        };
      }
    }

    yield {
      content: '',
      done: true,
      meta: {
        model: options.model || this.config.model || 'claude-3-sonnet-20240229',
        provider: this.getProviderName(),
      },
    };
  }
}