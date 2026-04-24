import { OpenAIProvider } from './providers/openai';
import { GeminiProvider } from './providers/gemini';
import { AnthropicProvider } from './providers/anthropic';
import { BaseAIProvider, AIMessage, AIResponse, AIOptions, AIProviderConfig, AIStreamChunk } from './types';

type ProviderType = 'openai' | 'gemini' | 'anthropic';

class AIService {
  private providers: Map<string, BaseAIProvider> = new Map();

  /**
   * Register a provider with configuration
   */
  registerProvider(type: ProviderType, config: AIProviderConfig): void {
    let provider: BaseAIProvider;

    switch (type) {
      case 'openai':
        provider = new OpenAIProvider(config);
        break;
      case 'gemini':
        provider = new GeminiProvider(config);
        break;
      case 'anthropic':
        provider = new AnthropicProvider(config);
        break;
      default:
        throw new Error(`Unknown provider type: ${type}`);
    }

    this.providers.set(type, provider);
  }

  /**
   * Generate AI response
   */
  async generate(options: {
    provider: ProviderType;
    messages: AIMessage[];
    aiOptions?: AIOptions;
  }): Promise<AIResponse> {
    const provider = this.providers.get(options.provider);
    if (!provider) {
      throw new Error(`Provider ${options.provider} not registered`);
    }

    return provider.generate(options.messages, options.aiOptions);
  }

  /**
   * Generate streaming AI response
   */
  async *generateStream(options: {
    provider: ProviderType;
    messages: AIMessage[];
    aiOptions?: AIOptions;
  }): AsyncGenerator<AIStreamChunk> {
    const provider = this.providers.get(options.provider);
    if (!provider) {
      throw new Error(`Provider ${options.provider} not registered`);
    }

    yield* provider.generateStream(options.messages, options.aiOptions);
  }

  /**
   * Get available providers
   */
  getAvailableProviders(): string[] {
    return Array.from(this.providers.keys());
  }

  /**
   * Check if provider is available
   */
  hasProvider(type: ProviderType): boolean {
    return this.providers.has(type);
  }

  /**
   * Unregister a provider
   */
  unregisterProvider(type: ProviderType): void {
    this.providers.delete(type);
  }
}

export const ai = new AIService();

// Export types for convenience
export type { AIMessage, AIResponse, AIOptions, AIProviderConfig, AIStreamChunk };