import { FastifyRequest, FastifyReply } from 'fastify';
import { OpenRouterProvider } from '../providers/openrouter.provider';
import { StreamService } from './stream.service';
import { config } from '../config/index';
import { query, queryOne } from '../config/database';
import { Message, MessageContent } from '../types/index';
import logger from '../utils/logger';
import { metrics } from '../utils/metrics';

export interface ChatRequest {
  messages: Message[];
  stream?: boolean;
  options?: {
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
  private provider: OpenRouterProvider;
  private maxMessages: number;
  private maxChars: number;

  constructor() {
    this.provider = new OpenRouterProvider(
      config.ai.openrouter.apiKey || '',
      config.ai.defaultModel
    );
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

    // Ensure AI provider is configured
    if (!config.ai.openrouter.apiKey) {
      reply.code(500).send({
        success: false,
        error: 'OpenRouter API key is not configured. Please set OPENROUTER_API_KEY in the environment.',
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

  /**
   * Handle non-streaming chat
   */
  private async handleNonStreamingChat(
    reply: FastifyReply,
    systemPrompt: string,
    messages: Message[],
    options?: ChatRequest['options']
  ): Promise<void> {
    try {
      const startTime = Date.now();
      const model = this.normalizeModel(options?.model || config.ai.frontendModel);

      const response = await this.provider.chat(systemPrompt, messages, {
        model,
        temperature: options?.temperature || config.ai.temperature,
        maxTokens: options?.maxTokens || config.ai.maxTokens,
      });

      const executionTime = Date.now() - startTime;

      // Record metrics
      metrics.aiRequestsTotal.labels(response.meta.provider, response.meta.model, 'true').inc();

      if (response.meta.tokensUsed) {
        metrics.aiTokensUsed
          .labels(response.meta.provider, response.meta.model)
          .inc(response.meta.tokensUsed);
      }

      // Log usage
      await this.logUsage(response.meta, executionTime);

      reply.send({
        success: true,
        content: response.content,
        meta: {
          ...response.meta,
          executionTimeMs: executionTime,
        },
      });
    } catch (error: any) {
      logger.error('Chat error:', error);

      // Record failed request metric
      metrics.aiRequestsTotal.labels('unknown', 'unknown', 'false').inc();

      reply.code(500).send({
        success: false,
        error: error.message || 'Failed to process chat request',
      });
    }
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

      for await (const chunk of this.provider.streamChat(systemPrompt, messages, {
        model: this.normalizeModel(options?.model || config.ai.frontendModel),
        temperature: options?.temperature || config.ai.temperature,
        maxTokens: options?.maxTokens || config.ai.maxTokens,
      })) {
        if (chunk.content) {
          // Step 3: Generating response (only send once when content starts)
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

          // Record metrics
          metrics.aiRequestsTotal.labels(chunk.meta.provider, chunk.meta.model, 'true').inc();

          if (chunk.meta.tokensUsed) {
            metrics.aiTokensUsed
              .labels(chunk.meta.provider, chunk.meta.model)
              .inc(chunk.meta.tokensUsed);
          }

          // Log usage
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

      if (!finalMeta) {
        const executionTime = Date.now() - startTime;
        const normalizedModel = this.normalizeModel(options?.model || config.ai.frontendModel);
        StreamService.sendDone(reply, {
          model: normalizedModel,
          provider: 'openrouter',
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
