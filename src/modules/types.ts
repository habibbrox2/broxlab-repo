export interface AIMessage {
  role: 'user' | 'assistant' | 'system';
  content: string;
}

export interface AIResponse {
  content: string;
  meta?: {
    model: string;
    provider: string;
    tokensUsed?: number;
    finishReason?: string;
  };
}

export interface AIStreamChunk {
  content: string;
  done?: boolean;
  meta?: AIResponse['meta'];
}

export interface AIOptions {
  model?: string;
  maxTokens?: number;
  temperature?: number;
  topP?: number;
  presencePenalty?: number;
  frequencyPenalty?: number;
}

export interface AIProviderConfig {
  apiKey: string;
  baseURL?: string;
  model?: string;
}

export abstract class BaseAIProvider {
  protected config: AIProviderConfig;

  constructor(config: AIProviderConfig) {
    this.config = config;
  }

  abstract generate(
    messages: AIMessage[],
    options?: AIOptions
  ): Promise<AIResponse>;

  abstract generateStream(
    messages: AIMessage[],
    options?: AIOptions
  ): AsyncGenerator<AIStreamChunk>;

  getProviderName(): string {
    return this.constructor.name.replace('Provider', '').toLowerCase();
  }
}