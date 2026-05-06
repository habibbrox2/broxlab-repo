import { FastifyRequest, FastifyReply } from 'fastify';
import { OpenRouterProvider } from '../providers/openrouter.provider';
import { config } from '../config/index';
import { query, queryOne } from '../config/database';
import { Message } from '../types/index';
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

    // Validate each message
    const valid = limited.filter((msg) => {
      if (!msg || typeof msg !== 'object') return false;
      if (!['user', 'assistant', 'system'].includes(msg.role)) return false;
      if (typeof msg.content !== 'string') return false;
      if (msg.content.length > this.maxChars) return false;
      return true;
    });

    return valid;
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
    reply.raw.writeHead(200, {
      'Content-Type': 'text/event-stream',
      'Cache-Control': 'no-cache',
      Connection: 'keep-alive',
      'X-Accel-Buffering': 'no',
    });
    reply.raw.flushHeaders?.();

    try {
      const startTime = Date.now();
      let fullContent = '';
      let model = '';

      for await (const chunk of this.provider.streamChat(systemPrompt, messages, {
        model: this.normalizeModel(options?.model || config.ai.frontendModel),
        temperature: options?.temperature || config.ai.temperature,
        maxTokens: options?.maxTokens || config.ai.maxTokens,
      })) {
        if (chunk.content) {
          fullContent += chunk.content;
          reply.raw.write(`data: ${JSON.stringify({ content: chunk.content })}\n\n`);
        }

        if (chunk.meta) {
          model = chunk.meta.model;
          const executionTime = Date.now() - startTime;

          // Send final meta
          reply.raw.write(
            `data: ${JSON.stringify({
              done: true,
              meta: {
                ...chunk.meta,
                executionTimeMs: executionTime,
              },
            })}\n\n`
          );

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

      reply.raw.end();
    } catch (error: any) {
      logger.error('Streaming chat error:', error);

      // Record failed request metric
      metrics.aiRequestsTotal.labels('unknown', 'unknown', 'false').inc();

      reply.raw.write(`data: ${JSON.stringify({ error: error.message || 'Stream error' })}\n\n`);
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
