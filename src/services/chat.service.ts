import { FastifyRequest, FastifyReply } from 'fastify';
import { OpenRouterProvider } from '../providers/openrouter.provider';
import { OllamaProvider } from '../providers/ollama.provider';
import { OpenAIProvider } from '../providers/openai.provider';
import { AnthropicProvider } from '../providers/anthropic.provider';
import { GeminiProvider } from '../providers/gemini.provider';
import { KiloProvider } from '../providers/kilo.provider';
import { BaseAIProvider } from '../providers/base.provider';
import { StreamService } from './stream.service';
import aiProviderService from './ai-provider.service';
import { aiModelService, ProviderModel } from './ai-models.service';
import { config } from '../config/index';
import { query, queryOne } from '../config/database';
import { Message, MessageContent } from '../types/index';
import logger from '../utils/logger';
import { metrics } from '../utils/metrics';

export interface ChatRequest {
  messages: Message[];
  stream?: boolean;
  options?: {
    provider?: string;
    model?: string;
    temperature?: number;
    maxTokens?: number;
  };
  context?: Record<string, any>;
  system?: string;
  visitorToken?: string;
}

export interface ChatResponse {
  success: boolean;
  content?: string;
  meta?: {
    model: string;
    provider: string;
    tokensUsed?: number;
    finishReason?: string;
    executionTimeMs?: number;
  };
  error?: string;
}

export class ChatService {
  private maxMessages: number;
  private maxChars: number;

  constructor() {
    this.maxMessages = 20;
    this.maxChars = 4000;
  }

  /**
   * Handle chat request
   */
  async handleChat(request: FastifyRequest, reply: FastifyReply, isAdmin: boolean): Promise<void> {
    const body = request.body as ChatRequest;
    const { messages, stream, options, context, system } = body;

    // Normalize messages
    const normalizedMessages = this.normalizeMessages(messages);
    if (!normalizedMessages) {
      reply.code(400).send({
        success: false,
        error: 'Invalid messages format',
      });
      return;
    }

    // Build system prompt
    const systemPrompt = await this.buildSystemPrompt(isAdmin, context, system);

    // Handle streaming or non-streaming
    if (stream) {
      await this.handleStreamingChat(reply, systemPrompt, normalizedMessages, options);
    } else {
      await this.handleNonStreamingChat(reply, systemPrompt, normalizedMessages, options);
    }
  }

  /**
   * Normalize messages array
   */
  private normalizeMessages(messages: Message[]): Message[] | null {
    if (!Array.isArray(messages) || messages.length === 0) {
      return null;
    }

    // Limit messages
    const limited = messages.slice(-this.maxMessages);

    // Validate and normalize each message
    const valid: Message[] = [];

    for (const msg of limited) {
      if (!msg || typeof msg !== 'object') continue;
      if (!['user', 'assistant', 'system'].includes(msg.role)) continue;

      // Handle content normalization
      let normalizedContent: string | MessageContent[];

      if (typeof msg.content === 'string') {
        // Simple text message
        if (msg.content.length === 0 || msg.content.length > this.maxChars) continue;
        normalizedContent = msg.content;
      } else if (Array.isArray(msg.content)) {
        // Vision message with content parts
        const normalizedParts: MessageContent[] = [];

        for (const part of msg.content) {
          if (part.type === 'text' && typeof part.text === 'string' && part.text.length > 0) {
            normalizedParts.push({
              type: 'text',
              text: part.text.length > this.maxChars ? part.text.substring(0, this.maxChars) : part.text
            });
          } else if (part.type === 'image_url' && part.image_url && typeof part.image_url.url === 'string') {
            // Only include url, remove extra fields like name, mime, size
            normalizedParts.push({
              type: 'image_url',
              image_url: {
                url: part.image_url.url
              }
            });
          }
        }

        if (normalizedParts.length === 0) continue;
        normalizedContent = normalizedParts;
      } else {
        // Invalid content type
        continue;
      }

      valid.push({
        role: msg.role,
        content: normalizedContent
      });
    }

    return valid.length > 0 ? valid : null;
  }

  /**
   * Build system prompt
   */
  private async buildSystemPrompt(
    isAdmin: boolean,
    context?: Record<string, any>,
    overrideSystem?: string
  ): Promise<string> {
    const isAdminContext = isAdmin || context?.isAdmin === true || context?.admin === true;
    let prompt = overrideSystem && overrideSystem.trim() !== ''
      ? overrideSystem.trim()
      : isAdminContext
        ? 'You are an AI assistant for BroxLab admin panel. Help administrators manage their website efficiently.'
        : 'You are a helpful AI assistant for BroxLab. Provide accurate and helpful responses to users.';

    // Add context if provided
    if (context && Object.keys(context).length > 0) {
      prompt += '\n\n[USER CONTEXT]\n';
      for (const [key, value] of Object.entries(context)) {
        if (typeof value === 'string' || typeof value === 'number') {
          prompt += `${key.charAt(0).toUpperCase() + key.slice(1)}: ${value}\n`;
        }
      }
    }

    return prompt;
  }

  /**
   * Normalize model IDs for OpenRouter compatibility
   */
  private normalizeModel(model?: string): string {
    const normalized = (model || config.ai.frontendModel).trim();
    const modelMap: Record<string, string> = {
      'openrouter/gpt-4o': 'openrouter/auto',
    };

    return modelMap[normalized] ?? normalized;
  }

  private normalizeProviderName(provider?: string): string {
    return (provider || '').trim().toLowerCase();
  }

  private getProviderApiKey(providerName: string, apiKey: string | null): string {
    if (providerName === 'openrouter') {
      return apiKey || config.ai.openrouter.apiKey || '';
    }

    if (providerName === 'openai') {
      return apiKey || config.ai.openai.apiKey || '';
    }

    if (providerName === 'anthropic') {
      return apiKey || config.ai.anthropic.apiKey || '';
    }

    if (providerName === 'google' || providerName === 'gemini') {
      return apiKey || config.ai.google.apiKey || '';
    }

    if (providerName === 'kilo') {
      return apiKey || config.ai.kilo.apiKey || '';
    }

    if (providerName === 'ollama') {
      return apiKey || process.env.OLLAMA_API_KEY || '';
    }

    return apiKey || '';
  }

  private createProviderInstance(
    providerName: string,
    apiKey: string,
    model: string
  ): BaseAIProvider | null {
    switch (providerName) {
      case 'openrouter':
        return new OpenRouterProvider(apiKey, model);
      case 'openai':
        return new OpenAIProvider(apiKey, model);
      case 'anthropic':
        return new AnthropicProvider(apiKey, model);
      case 'google':
      case 'gemini':
        return new GeminiProvider(apiKey, model);
      case 'kilo':
        return new KiloProvider(apiKey, model);
      case 'ollama':
        return new OllamaProvider(apiKey, model);
      default:
        return null;
    }
  }

  private determineModelForProvider(
    providerName: string,
    requestedModel?: string,
    supportedModels: ProviderModel[] = []
  ): string {
    const normalizedRequest = (requestedModel || '').trim();
    if (normalizedRequest) {
      if (
        supportedModels.some((model) => model.id === normalizedRequest) ||
        normalizedRequest.startsWith(`${providerName}/`) ||
        providerName === 'openrouter'
      ) {
        return this.normalizeModel(normalizedRequest);
      }

      return normalizedRequest;
    }

    const defaultModel =
      supportedModels.find((model) => model.default)?.id ||
      supportedModels[0]?.id ||
      (providerName === 'openrouter'
        ? config.ai.defaultModel
        : providerName === 'ollama'
          ? 'llama2'
          : config.ai.defaultModel);

    return this.normalizeModel(defaultModel);
  }

  private isProviderFallbackError(error: any): boolean {
    const message = String(error?.message || '').toLowerCase();
    return /insufficient credits|402|quota|rate limit|invalid api key|unauthorized|forbidden|authentication|account never purchased|insufficient balance/.test(message);
  }

  private async getCandidateProviders(
    preferredProvider?: string
  ): Promise<Array<{ providerName: string; apiKey: string; models: ProviderModel[] }>> {
    const allProviders = await aiModelService.getActiveProviderModels();
    const providerNames = Object.keys(allProviders.providers);
    const orderedProviders = new Set<string>();
    const preferred = this.normalizeProviderName(preferredProvider);
    const defaultProvider = this.normalizeProviderName(config.ai.defaultProvider);

    if (preferred && providerNames.includes(preferred)) {
      orderedProviders.add(preferred);
    }

    if (defaultProvider && providerNames.includes(defaultProvider)) {
      orderedProviders.add(defaultProvider);
    }

    for (const providerName of providerNames) {
      orderedProviders.add(providerName);
    }

    const candidates: Array<{ providerName: string; apiKey: string; models: ProviderModel[] }> = [];

    for (const providerName of orderedProviders) {
      const apiKey = this.getProviderApiKey(
        providerName,
        await aiProviderService.getAPIKey(providerName)
      );

      if (!apiKey && providerName !== 'ollama') {
        continue;
      }

      candidates.push({
        providerName,
        apiKey,
        models: allProviders.providers[providerName] || [],
      });
    }

    if (candidates.length === 0 && config.ai.openrouter.apiKey) {
      candidates.push({
        providerName: 'openrouter',
        apiKey: config.ai.openrouter.apiKey,
        models: allProviders.providers['openrouter'] || [],
      });
    }

    return candidates;
  }

  /**
   * Handle non-streaming chat
   */
  private async handleNonStreamingChat(
    reply: FastifyReply,
    systemPrompt: string,
    messages: Message[],
    options?: ChatRequest['options']
  ): Promise<void> {
    const allProviders = await aiModelService.getActiveProviderModels();
    const candidateProviders = await this.getCandidateProviders(options?.provider);

    if (candidateProviders.length === 0) {
      const activeProviderNames = Object.keys(allProviders.providers);
      const providerHint = activeProviderNames.length > 0
        ? ` Active providers configured: ${activeProviderNames.join(', ')}.`
        : '';

      reply.code(500).send({
        success: false,
        error: `No active AI providers are configured or API key(s) are missing.${providerHint} Provide the provider API key via ai_settings (for example openrouter_api_key) or set api_key_env on the ai_providers row for environment-based key lookup.`,
      });
      return;
    }

    let lastError: any = null;

    for (const candidate of candidateProviders) {
      const model = this.determineModelForProvider(
        candidate.providerName,
        options?.model,
        candidate.models
      );
      const providerInstance = this.createProviderInstance(
        candidate.providerName,
        candidate.apiKey,
        model
      );

      if (!providerInstance) {
        continue;
      }

      try {
        const startTime = Date.now();
        const response = await providerInstance.chat(systemPrompt, messages, {
          model,
          temperature: options?.temperature || config.ai.temperature,
          maxTokens: options?.maxTokens || config.ai.maxTokens,
        });

        const executionTime = Date.now() - startTime;

        metrics.aiRequestsTotal.labels(response.meta.provider, response.meta.model, 'true').inc();

        if (response.meta.tokensUsed) {
          metrics.aiTokensUsed
            .labels(response.meta.provider, response.meta.model)
            .inc(response.meta.tokensUsed);
        }

        await this.logUsage(response.meta, executionTime);

        reply.send({
          success: true,
          content: response.content,
          meta: {
            ...response.meta,
            executionTimeMs: executionTime,
          },
        });
        return;
      } catch (error: any) {
        lastError = error;
        logger.warn('AI provider failed, trying next provider if available', {
          provider: candidate.providerName,
          model,
          error: error?.message || error,
        });

        if (!this.isProviderFallbackError(error)) {
          logger.error('Non-retryable AI provider failure:', error);
          break;
        }
      }
    }

    metrics.aiRequestsTotal.labels('unknown', 'unknown', 'false').inc();
    reply.code(500).send({
      success: false,
      error:
        lastError?.message || 'Failed to process chat request with any available provider.',
    });
  }

  /**
   * Handle streaming chat with SSE
   */
  private async handleStreamingChat(
    reply: FastifyReply,
    systemPrompt: string,
    messages: Message[],
    options?: ChatRequest['options']
  ): Promise<void> {
    StreamService.initSSE(reply);

    try {
      const startTime = Date.now();
      let fullContent = '';
      let model = '';
      let finalMeta: any = null;

      // Step 0: Understanding request
      StreamService.sendStatus(reply, 'Understanding request...', 0);

      // Step 1: Planning response
      StreamService.sendStatus(reply, 'Planning response...', 1);

      // Step 2: Calling AI provider
      StreamService.sendStatus(reply, 'Calling AI provider...', 2);

      const candidateProviders = await this.getCandidateProviders(options?.provider);
      if (candidateProviders.length === 0) {
        throw new Error('No active AI providers are configured.');
      }

      let lastError: any = null;
      let usedProviderName = 'openrouter';
      let usedModel = this.normalizeModel(options?.model || config.ai.frontendModel);
      let providerInstance: BaseAIProvider | null = null;

      for (const candidate of candidateProviders) {
        const candidateModel = this.determineModelForProvider(
          candidate.providerName,
          options?.model,
          candidate.models
        );

        const instance = this.createProviderInstance(
          candidate.providerName,
          candidate.apiKey,
          candidateModel
        );

        if (!instance) {
          continue;
        }

        providerInstance = instance;
        usedProviderName = candidate.providerName;
        usedModel = candidateModel;

        try {
          for await (const chunk of providerInstance.streamChat(systemPrompt, messages, {
            model: usedModel,
            temperature: options?.temperature || config.ai.temperature,
            maxTokens: options?.maxTokens || config.ai.maxTokens,
          })) {
            if (chunk.content) {
              if (fullContent === '') {
                StreamService.sendStatus(reply, 'Generating final answer...', 3);
              }
              fullContent += chunk.content;
              StreamService.sendChunk(reply, { content: chunk.content });
            }

            if (chunk.meta) {
              model = chunk.meta.model;
              const executionTime = Date.now() - startTime;
              finalMeta = {
                ...chunk.meta,
                executionTimeMs: executionTime,
              };

              StreamService.sendDone(reply, finalMeta);

              metrics.aiRequestsTotal.labels(chunk.meta.provider, chunk.meta.model, 'true').inc();

              if (chunk.meta.tokensUsed) {
                metrics.aiTokensUsed
                  .labels(chunk.meta.provider, chunk.meta.model)
                  .inc(chunk.meta.tokensUsed);
              }

              await this.logUsage(
                {
                  model,
                  provider: chunk.meta.provider,
                  finishReason: chunk.meta.finishReason,
                },
                executionTime
              );
            }
          }

          break;
        } catch (error: any) {
          lastError = error;
          logger.warn('AI streaming provider failed, trying next provider if available', {
            provider: candidate.providerName,
            model: usedModel,
            error: error?.message || error,
          });

          if (!this.isProviderFallbackError(error)) {
            throw error;
          }

          providerInstance = null;
          continue;
        }
      }

      if (!providerInstance) {
        throw lastError || new Error('Failed to start stream with any available provider.');
      }

      if (!finalMeta) {
        const executionTime = Date.now() - startTime;
        StreamService.sendDone(reply, {
          model: usedModel,
          provider: usedProviderName,
          executionTimeMs: executionTime,
        });
      }

      reply.raw.end();
    } catch (error: any) {
      logger.error('Streaming chat error:', error);

      // Record failed request metric
      metrics.aiRequestsTotal.labels('unknown', 'unknown', 'false').inc();

      StreamService.sendError(reply, error.message || 'Stream error');
      reply.raw.end();
    }
  }

  /**
   * Log usage to database
   */
  private async logUsage(meta: any, executionTime: number): Promise<void> {
    try {
      // Check if usage table exists
      const tableExists = await queryOne(
        `SELECT COUNT(*) as count FROM information_schema.tables 
                 WHERE table_schema = DATABASE() AND table_name = 'ai_chat_usage'`
      );

      if (tableExists && tableExists.count > 0) {
        await query(
          `INSERT INTO ai_chat_usage 
                     (provider, model, tokens_used, finish_reason, execution_time_ms, created_at)
                     VALUES (?, ?, ?, ?, NOW())`,
          [
            meta.provider || 'openrouter',
            meta.model || 'unknown',
            meta.tokensUsed || 0,
            meta.finishReason || 'unknown',
            executionTime,
          ]
        );
      }
    } catch (error) {
      logger.error('Failed to log usage:', error);
    }
  }
}
