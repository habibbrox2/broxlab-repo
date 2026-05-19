// Global type definitions
import { z } from 'zod';

export interface MessageContentText {
    type: 'text';
    text: string;
}

export interface MessageContentImage {
    type: 'image_url';
    image_url: {
        url: string;
    };
}

export type MessageContent = MessageContentText | MessageContentImage;

export interface Message {
    role: 'system' | 'user' | 'assistant';
    content: string | MessageContent[];
}

export interface ChatRequest {
    messages: Message[];
    stream?: boolean;
    visitorToken?: string;
    context?: Record<string, any>;
    options?: ChatOptions;
}

export interface ChatOptions {
    provider?: string;
    model?: string;
    maxTokens?: number;
    temperature?: number;
    topP?: number;
    presencePenalty?: number;
    frequencyPenalty?: number;
}

export interface ChatResponse {
    success: boolean;
    content?: string;
    error?: string;
    meta?: ResponseMeta;
}

export interface ResponseMeta {
    model: string;
    provider: string;
    tokensUsed?: number;
    inputTokens?: number;
    outputTokens?: number;
    finishReason?: string;
    executionTimeMs?: number;
}

export interface StreamChunk {
    type?: 'content' | 'meta' | 'error' | 'status' | 'step' | 'tool';
    event?: 'status' | 'step' | 'tool' | 'content' | 'meta';
    content?: string;
    done?: boolean;
    meta?: ResponseMeta;
    error?: string;
    step?: number;
    status?: string;
    toolName?: string;
    toolLabel?: string;
    steps?: Array<{ id: string; label: string }>;
}

export interface ToolContext {
    userId?: number;
    isAdmin: boolean;
    database: any;
    redis: any;
    registry?: any; // Reference to ToolRegistry for tools that need it
}

export interface ToolResult {
    success: boolean;
    data?: any;
    error?: string;
    cached?: boolean;
    executionTimeMs?: number;
}

export interface ToolDefinition {
    name: string;
    displayName: string;
    description: string;
    parameters: z.ZodSchema;
    namespace?: string;
    requiresAuth: boolean;
    cacheable: boolean;
    timeout: number;
    maxRetries: number;
    execute: (args: any, context: ToolContext) => Promise<any>;
}

export interface ProviderConfig {
    apiKey?: string;
    baseURL?: string;
    model: string;
}

export interface CircuitBreakerState {
    failures: number;
    lastFailure: number;
    state: 'closed' | 'open' | 'half_open';
}
