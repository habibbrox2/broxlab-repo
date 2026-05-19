import { GoogleGenerativeAI, GenerativeModel } from '@google/generative-ai';
import { BaseAIProvider, AIMessage, AIResponse, AIOptions, AIProviderConfig, AIStreamChunk } from '../types';

export class GeminiProvider extends BaseAIProvider {
  private model: GenerativeModel;

  constructor(config: AIProviderConfig) {
    super(config);
    const genAI = new GoogleGenerativeAI(config.apiKey);
    this.model = genAI.getGenerativeModel({ model: config.model || 'gemini-1.5-flash' });
  }

  async generate(messages: AIMessage[], options: AIOptions = {}): Promise<AIResponse> {
    // Gemini doesn't support system messages in the same way, so we'll combine system + user
    const systemMessage = messages.find(m => m.role === 'system');
    const userMessages = messages.filter(m => m.role !== 'system');

    let prompt = '';
    if (systemMessage) {
      prompt += `${systemMessage.content}\n\n`;
    }
    prompt += userMessages.map(m => m.content).join('\n');

    const result = await this.model.generateContent(prompt);
    const response = result.response;
    const text = response.text();

    return {
      content: text,
      meta: {
        model: this.config.model || 'gemini-pro',
        provider: this.getProviderName(),
        // Gemini doesn't provide token usage in the same way
      },
    };
  }

  async *generateStream(messages: AIMessage[], options: AIOptions = {}): AsyncGenerator<AIStreamChunk> {
    // Gemini streaming support
    const systemMessage = messages.find(m => m.role === 'system');
    const userMessages = messages.filter(m => m.role !== 'system');

    let prompt = '';
    if (systemMessage) {
      prompt += `${systemMessage.content}\n\n`;
    }
    prompt += userMessages.map(m => m.content).join('\n');

    const result = await this.model.generateContentStream(prompt);

    for await (const chunk of result.stream) {
      const chunkText = chunk.text();
      if (chunkText) {
        yield {
          content: chunkText,
          done: false,
        };
      }
    }

    yield {
      content: '',
      done: true,
      meta: {
        model: this.config.model || 'gemini-pro',
        provider: this.getProviderName(),
      },
    };
  }
}