import { Message, ChatOptions, ResponseMeta } from '../types/index';
import { BaseAIProvider } from './base.provider';
import logger from '../utils/logger';

export class OllamaProvider extends BaseAIProvider {
  constructor(apiKey?: string, model: string = 'llama2') {
    super({
      apiKey: apiKey || process.env.OLLAMA_API_KEY || '',
      baseURL: process.env.OLLAMA_ENDPOINT || process.env.OLLAMA_BASE_URL || 'https://ollama.com/api',
      model,
      name: 'ollama',
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

      logger.debug('Ollama chat request:', {
        model: requestOptions.model,
        messageCount: messages.length,
      });

      const response = await this.client.chat.completions.create(requestOptions);

      const content = response.choices[0]?.message?.content || '';
      const meta = this.extractMeta(response);
      meta.executionTimeMs = Date.now() - startTime;

      logger.debug('Ollama chat response:', {
        model: meta.model,
        tokensUsed: meta.tokensUsed,
        executionTimeMs: meta.executionTimeMs,
      });

      return { content, meta };
    } catch (error) {
      logger.error('Ollama chat error:', error);
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

      logger.debug('Ollama stream request:', {
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
            provider: 'ollama',
            finishReason: chunk.choices[0].finish_reason,
            executionTimeMs: Date.now() - startTime,
          };

          logger.debug('Ollama stream complete:', {
            model,
            finishReason: meta.finishReason,
            executionTimeMs: meta.executionTimeMs,
          });

          yield { content: '', meta };
        }
      }
    } catch (error) {
      logger.error('Ollama stream error:', error);
      throw error;
    }
  }
}

export default OllamaProvider;