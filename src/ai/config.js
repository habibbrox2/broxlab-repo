/**
 * AI Service Configuration
 * 
 * Centralized configuration for Node.js AI services
 * Includes feature flags for gradual migration from any legacy systems
 */

// =============================================================================
// FEATURE FLAGS
// =============================================================================

export const FEATURE_FLAGS = {
    // Enable/disable AI services
    AI_ENABLED: process.env.AI_ENABLED === 'true',

    // Use direct SDKs (this implementation) vs legacy/other
    USE_DIRECT_SDK: process.env.USE_DIRECT_SDK !== 'false',

    // Enable RAG pipeline
    ENABLE_RAG: process.env.ENABLE_RAG !== 'false',

    // Enable streaming responses
    ENABLE_STREAMING: process.env.ENABLE_STREAMING !== 'false',

    // Enable caching
    ENABLE_CACHING: process.env.ENABLE_CACHING !== 'false',

    // Enable retry logic
    ENABLE_RETRY: process.env.ENABLE_RETRY !== 'false',

    // Enable metrics collection
    ENABLE_METRICS: process.env.ENABLE_METRICS === 'true',

    // Enable fallback providers
    ENABLE_FALLBACK: process.env.ENABLE_FALLBACK !== 'false',

    // ========== NEW FEATURES ==========
    
    // Enable CV Enhancement (Node.js AI)
    CV_ENHANCEMENT_ENABLED: process.env.CV_ENHANCEMENT_ENABLED === 'true',

    // Enable KB Self-Healing
    KB_SELF_HEALING_ENABLED: process.env.KB_SELF_HEALING_ENABLED === 'true',

    // Auto-improve KB entries (requires KB_SELF_HEALING_ENABLED)
    KB_AUTO_IMPROVE: process.env.KB_AUTO_IMPROVE === 'true',

    // KB Quality threshold (0-100)
    KB_QUALITY_THRESHOLD: parseInt(process.env.KB_QUALITY_THRESHOLD || '50'),

    // KB lookback days for outdated content detection
    KB_LOOKBACK_DAYS: parseInt(process.env.KB_LOOKBACK_DAYS || '30'),

    // Use Node.js AI server instead of PHP
    USE_NODEJS_AI_SERVER: process.env.USE_NODEJS_AI_SERVER === 'true',

    // Node.js AI server URL (for PHP to call)
    NODEJS_AI_SERVER_URL: process.env.NODEJS_AI_SERVER_URL || 'http://localhost:3001',
};

// =============================================================================
// PROVIDER CONFIGURATIONS
// =============================================================================

export const PROVIDER_CONFIGS = {
    // Google AI (Gemini)
    google: {
        name: 'Google AI',
        apiKey: process.env.GOOGLE_API_KEY || process.env.GEMINI_API_KEY || '',
        baseUrl: 'https://generativelanguage.googleapis.com/v1',
        defaultModel: process.env.GOOGLE_MODEL || 'gemini-2.0-flash-exp',
        models: [
            'gemini-2.0-flash-exp',
            'gemini-1.5-pro',
            'gemini-1.5-flash',
            'gemini-1.5-flash-8b',
        ],
        supportsStreaming: true,
        supportsVision: true,
        maxTokens: 32768,
    },

    // OpenAI
    openai: {
        name: 'OpenAI',
        apiKey: process.env.OPENAI_API_KEY || '',
        baseUrl: 'https://api.openai.com/v1',
        defaultModel: process.env.OPENAI_MODEL || 'gpt-4o-mini',
        models: [
            'gpt-4o',
            'gpt-4o-mini',
            'gpt-4-turbo',
            'gpt-3.5-turbo',
        ],
        supportsStreaming: true,
        supportsVision: true,
        maxTokens: 128000,
    },

    // Anthropic
    anthropic: {
        name: 'Anthropic',
        apiKey: process.env.ANTHROPIC_API_KEY || '',
        baseUrl: 'https://api.anthropic.com/v1',
        defaultModel: process.env.ANTHROPIC_MODEL || 'claude-3-5-sonnet-20241022',
        models: [
            'claude-3-5-sonnet-20241022',
            'claude-3-5-haiku-20241022',
            'claude-3-opus-20240229',
            'claude-3-sonnet-20240229',
            'claude-3-haiku-20240307',
        ],
        supportsStreaming: true,
        supportsVision: true,
        maxTokens: 200000,
    },

    // OpenRouter (aggregator)
    openrouter: {
        name: 'OpenRouter',
        apiKey: process.env.OPENROUTER_API_KEY || '',
        baseUrl: 'https://openrouter.ai/api/v1',
        defaultModel: process.env.OPENROUTER_MODEL || 'openrouter/auto',
        models: [
            'openrouter/auto',
            'openai/gpt-4o',
            'openai/gpt-4o-mini',
            'anthropic/claude-3.5-sonnet',
            'google/gemini-2.0-flash-exp:free',
        ],
        supportsStreaming: true,
        supportsVision: true,
        maxTokens: 128000,
    },

    // Ollama (local)
    ollama: {
        name: 'Ollama',
        apiKey: '', // No API key needed for local
        baseUrl: process.env.OLLAMA_BASE_URL || 'http://localhost:11434',
        defaultModel: process.env.OLLAMA_MODEL || 'llama3.2',
        models: [],
        supportsStreaming: true,
        supportsVision: false,
        maxTokens: 8192,
    },
};

// =============================================================================
// DEFAULT PROVIDER ORDER (fallback chain)
// =============================================================================

export const DEFAULT_FALLBACK_CHAIN = [
    'openai',
    'anthropic',
    'google',
    'openrouter',
    'ollama',
];

// =============================================================================
// REQUEST CONFIGURATION
// =============================================================================

export const REQUEST_CONFIG = {
    // Timeouts (ms)
    timeout: parseInt(process.env.AI_TIMEOUT || '30000', 10),

    // Retry configuration
    maxRetries: parseInt(process.env.AI_MAX_RETRIES || '3', 10),
    retryDelay: parseInt(process.env.AI_RETRY_DELAY || '1000', 10),
    retryBackoffMultiplier: parseFloat(process.env.AI_RETRY_BACKOFF || '2'),

    // Max tokens
    maxTokens: parseInt(process.env.AI_MAX_TOKENS || '4096', 10),

    // Temperature
    defaultTemperature: parseFloat(process.env.AI_TEMPERATURE || '0.7'),

    // Cache TTL (seconds)
    cacheTtl: parseInt(process.env.AI_CACHE_TTL || '3600', 10),
};

// =============================================================================
// PHP BACKEND CONFIGURATION
// =============================================================================

export const PHP_BACKEND = {
    baseUrl: process.env.PHP_BACKEND_URL || process.env.APP_URL || 'http://localhost',
};

// =============================================================================
// RAG CONFIGURATION
// =============================================================================

export const RAG_CONFIG = {
    // Vector store (qdrant)
    qdrant: {
        url: process.env.QDRANT_URL || 'http://localhost:6333',
        apiKey: process.env.QDRANT_API_KEY || '',
        collectionName: process.env.QDRANT_COLLECTION || 'broxlab_knowledge',
    },

    // Embedding configuration
    embedding: {
        provider: process.env.EMBEDDING_PROVIDER || 'openai',
        model: process.env.EMBEDDING_MODEL || 'text-embedding-3-small',
        dimensions: 1536,
    },

    // Retrieval configuration
    retrieval: {
        maxResults: parseInt(process.env.RAG_MAX_RESULTS || '5', 10),
        minScore: parseFloat(process.env.RAG_MIN_SCORE || '0.7'),
        rerank: process.env.RAG_RERANK === 'true',
    },
};

// =============================================================================
// METRICS CONFIGURATION
// =============================================================================

export const METRICS_CONFIG = {
    enabled: FEATURE_FLAGS.ENABLE_METRICS,

    // Metrics to track
    trackLatency: true,
    trackTokens: true,
    trackErrors: true,
    trackProviderFallbacks: true,

    // Log levels
    logLevel: process.env.AI_LOG_LEVEL || 'info',
};

// =============================================================================
// EXPORT DEFAULT CONFIG
// =============================================================================

export default {
    FEATURE_FLAGS,
    PROVIDER_CONFIGS,
    DEFAULT_FALLBACK_CHAIN,
    REQUEST_CONFIG,
    RAG_CONFIG,
    METRICS_CONFIG,
};
