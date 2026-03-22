/**
 * AI Router
 * 
 * Unified interface for all AI providers with:
 * - Provider fallback
 * - Retry logic
 * - Caching
 * - Metrics
 * 
 * This replaces any Genkit-based orchestration
 */

import {
    FEATURE_FLAGS,
    PROVIDER_CONFIGS,
    DEFAULT_FALLBACK_CHAIN,
    REQUEST_CONFIG,
} from './config.js';

import { GoogleProvider } from './providers/GoogleProvider.js';
import { OpenAIProvider } from './providers/OpenAIProvider.js';
import { AnthropicProvider } from './providers/AnthropicProvider.js';

import logger from './utils/Logger.js';
import cache from './utils/Cache.js';

class AIRouter {
    constructor(options = {}) {
        this.providers = {};
        this.fallbackChain = options.fallbackChain || DEFAULT_FALLBACK_CHAIN;
        this.defaultProvider = options.defaultProvider || 'openai';
        this.defaultModel = options.defaultModel || null;

        // Initialize providers
        this.initializeProviders();
    }

    /**
     * Initialize all configured providers
     */
    initializeProviders() {
        // Google AI
        if (PROVIDER_CONFIGS.google.apiKey) {
            try {
                this.providers.google = new GoogleProvider(PROVIDER_CONFIGS.google).initialize();
                logger.info('Initialized Google AI provider');
            } catch (error) {
                logger.warn('Failed to initialize Google AI provider', { error: error.message });
            }
        }

        // OpenAI
        if (PROVIDER_CONFIGS.openai.apiKey) {
            try {
                this.providers.openai = new OpenAIProvider(PROVIDER_CONFIGS.openai).initialize();
                logger.info('Initialized OpenAI provider');
            } catch (error) {
                logger.warn('Failed to initialize OpenAI provider', { error: error.message });
            }
        }

        // Anthropic
        if (PROVIDER_CONFIGS.anthropic.apiKey) {
            try {
                this.providers.anthropic = new AnthropicProvider(PROVIDER_CONFIGS.anthropic).initialize();
                logger.info('Initialized Anthropic provider');
            } catch (error) {
                logger.warn('Failed to initialize Anthropic provider', { error: error.message });
            }
        }

        // OpenRouter
        if (PROVIDER_CONFIGS.openrouter.apiKey) {
            try {
                // OpenRouter uses OpenAI-compatible API
                this.providers.openrouter = new OpenAIProvider({
                    ...PROVIDER_CONFIGS.openrouter,
                    baseUrl: 'https://openrouter.ai/api/v1',
                }).initialize();
                logger.info('Initialized OpenRouter provider');
            } catch (error) {
                logger.warn('Failed to initialize OpenRouter provider', { error: error.message });
            }
        }

        // Ollama (local)
        if (PROVIDER_CONFIGS.ollama.baseUrl) {
            try {
                this.providers.ollama = new OpenAIProvider({
                    ...PROVIDER_CONFIGS.ollama,
                    // Ollama uses OpenAI-compatible API
                    baseUrl: `${PROVIDER_CONFIGS.ollama.baseUrl}/v1`,
                }).initialize();
                logger.info('Initialized Ollama provider');
            } catch (error) {
                logger.warn('Failed to initialize Ollama provider', { error: error.message });
            }
        }
    }

    /**
     * Send chat request with automatic fallback
     */
    async chat(messages, provider = null, model = null, options = {}) {
        // Check if AI is enabled
        if (!FEATURE_FLAGS.AI_ENABLED) {
            throw new Error('AI services are disabled');
        }

        // Build options with defaults
        const opts = {
            temperature: options.temperature ?? REQUEST_CONFIG.defaultTemperature,
            maxTokens: options.maxTokens ?? REQUEST_CONFIG.maxTokens,
            ...options,
        };

        // Determine providers to try
        const providersToTry = this.getProviderChain(provider);

        let lastError = null;

        for (const prov of providersToTry) {
            const providerInstance = this.providers[prov];

            if (!providerInstance) {
                logger.debug(`Provider ${prov} not available, skipping`);
                continue;
            }

            const modelToUse = model || this.defaultModel || providerInstance.defaultModel;

            logger.logRequest(prov, modelToUse, opts);

            // Try cache first (if enabled)
            if (FEATURE_FLAGS.ENABLE_CACHING) {
                const cacheKey = cache.generateKey(prov, modelToUse, messages, opts);
                const cached = cache.get(cacheKey);

                if (cached) {
                    logger.info('Cache hit', { provider: prov, model: modelToUse });
                    return cached;
                }
            }

            // Attempt request with retry
            try {
                const response = await this.executeWithRetry(
                    providerInstance,
                    'chat',
                    [messages, modelToUse, opts]
                );

                // Cache successful response
                if (FEATURE_FLAGS.ENABLE_CACHING && response.content) {
                    const cacheKey = cache.generateKey(prov, modelToUse, messages, opts);
                    cache.set(cacheKey, response, REQUEST_CONFIG.cacheTtl);
                }

                return response;
            } catch (error) {
                lastError = error;
                logger.warn(`Provider ${prov} failed`, {
                    error: error.message,
                    model: modelToUse
                });

                // Log fallback
                const nextIdx = providersToTry.indexOf(prov) + 1;
                if (nextIdx < providersToTry.length && FEATURE_FLAGS.ENABLE_FALLBACK) {
                    logger.logFallback(prov, providersToTry[nextIdx], error.message);
                }

                // Continue to next provider
                continue;
            }
        }

        // All providers failed
        throw lastError || new Error('All AI providers failed');
    }

    /**
     * Stream chat response with automatic fallback
     */
    async *chatStream(messages, provider = null, model = null, options = {}) {
        if (!FEATURE_FLAGS.AI_ENABLED) {
            throw new Error('AI services are disabled');
        }

        if (!FEATURE_FLAGS.ENABLE_STREAMING) {
            // Fall back to non-streaming
            const response = await this.chat(messages, provider, model, options);
            yield { content: response.content, done: true };
            return;
        }

        const providersToTry = this.getProviderChain(provider);
        const opts = {
            temperature: options.temperature ?? REQUEST_CONFIG.defaultTemperature,
            maxTokens: options.maxTokens ?? REQUEST_CONFIG.maxTokens,
            ...options,
        };

        for (const prov of providersToTry) {
            const providerInstance = this.providers[prov];

            if (!providerInstance?.supportsStreaming) {
                continue;
            }

            const modelToUse = model || this.defaultModel || providerInstance.defaultModel;

            logger.logRequest(prov, modelToUse, { ...opts, streaming: true });

            try {
                for await (const chunk of await this.executeWithRetry(
                    providerInstance,
                    'chatStream',
                    [messages, modelToUse, opts]
                )) {
                    yield chunk;
                }
                return;
            } catch (error) {
                logger.warn(`Provider ${prov} streaming failed`, { error: error.message });
                continue;
            }
        }

        throw new Error('No streaming provider available');
    }

    /**
     * Generate embeddings
     */
    async embed(text, provider = 'openai', model = null) {
        if (!FEATURE_FLAGS.AI_ENABLED) {
            throw new Error('AI services are disabled');
        }

        const providerInstance = this.providers[provider];

        if (!providerInstance) {
            throw new Error(`Provider ${provider} not available`);
        }

        if (!providerInstance.embed) {
            throw new Error(`Provider ${provider} does not support embeddings`);
        }

        return await providerInstance.embed(text, model);
    }

    /**
     * Execute with retry logic
     */
    async executeWithRetry(provider, method, args, retryCount = 0) {
        const maxRetries = FEATURE_FLAGS.ENABLE_RETRY ? REQUEST_CONFIG.maxRetries : 0;

        try {
            return await provider[method](...args);
        } catch (error) {
            // Check if should retry
            if (retryCount < maxRetries && this.isRetryableError(error)) {
                const delay = REQUEST_CONFIG.retryDelay * Math.pow(REQUEST_CONFIG.retryBackoffMultiplier, retryCount);
                logger.info(`Retrying ${method} after ${delay}ms`, {
                    attempt: retryCount + 1,
                    maxRetries: maxRetries
                });

                await this.sleep(delay);
                return this.executeWithRetry(provider, method, args, retryCount + 1);
            }

            throw error;
        }
    }

    /**
     * Check if error is retryable
     */
    isRetryableError(error) {
        const message = error.message?.toLowerCase() || '';

        // Retry on timeout, rate limit, network errors
        const retryablePatterns = [
            'timeout',
            'rate limit',
            '429',
            '503',
            'network',
            'ECONNREFUSED',
            'ETIMEDOUT',
        ];

        return retryablePatterns.some(pattern => message.includes(pattern));
    }

    /**
     * Get provider chain based on preference
     */
    getProviderChain(preferredProvider) {
        if (preferredProvider) {
            // Use preferred + fallbacks
            const idx = this.fallbackChain.indexOf(preferredProvider);
            if (idx >= 0) {
                return [
                    preferredProvider,
                    ...this.fallbackChain.slice(idx + 1),
                    ...this.fallbackChain.slice(0, idx),
                ].filter(p => this.providers[p]);
            }
            return [preferredProvider];
        }

        // Use default chain
        return this.fallbackChain.filter(p => this.providers[p]);
    }

    /**
     * Check if provider is available
     */
    isProviderAvailable(provider) {
        return !!this.providers[provider];
    }

    /**
     * Get available providers
     */
    getAvailableProviders() {
        return Object.keys(this.providers);
    }

    /**
     * Clear provider cache
     */
    clearCache(provider = null) {
        if (provider) {
            cache.clearPrefix(`ai:${provider}:`);
        } else {
            cache.clear();
        }
    }

    /**
     * Sleep utility
     */
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

// Export singleton instance
const aiRouter = new AIRouter();

export default aiRouter;
export { AIRouter, aiRouter };
export const defaultAIRouter = aiRouter;
