import type { ToolDefinition, ToolContext, ToolResult, CircuitBreakerState } from '../types/index';
import logger from '../utils/logger';
import redis from '../config/redis';
import { metrics } from '../utils/metrics';

export class ToolRegistry {
    private tools = new Map<string, ToolDefinition>();
    private circuitBreaker = new Map<string, CircuitBreakerState>();
    private readonly CACHE_TTL = 60000; // 1 minute
    private readonly CIRCUIT_BREAKER_THRESHOLD = 5;
    private readonly CIRCUIT_BREAKER_TIMEOUT = 30000; // 30 seconds

    /**
     * Register a tool
     */
    register(definition: ToolDefinition): void {
        this.tools.set(definition.name, definition);
        logger.info('Tool registered', { name: definition.name });
    }

    /**
     * Get all registered tools
     */
    getAll(): ToolDefinition[] {
        return Array.from(this.tools.values());
    }

    /**
     * Get tool by name
     */
    get(name: string): ToolDefinition | undefined {
        return this.tools.get(name);
    }

    /**
     * Execute a tool with caching, circuit breaker, and retry logic
     */
    async execute(name: string, args: any, context: ToolContext): Promise<ToolResult> {
        const tool = this.tools.get(name);
        if (!tool) {
            throw new Error(`Tool '${name}' not found`);
        }

        // Check circuit breaker
        if (this.isCircuitOpen(name)) {
            throw new Error(`Circuit breaker open for tool '${name}'`);
        }

        // Check cache if tool is cacheable
        if (tool.cacheable) {
            const cached = await this.getFromCache(name, args);
            if (cached) {
                logger.debug('Cache hit', { tool: name });
                return {
                    success: true,
                    data: cached,
                    cached: true,
                };
            }
        }

        // Validate parameters
        try {
            const validated = tool.parameters.parse(args);
            args = validated;
        } catch (error: any) {
            logger.error('Parameter validation failed', {
                tool: name,
                error: error.message,
            });
            return {
                success: false,
                error: `Invalid parameters: ${error.message}`,
            };
        }

        // Add registry to context for tools that need it
        const enhancedContext: ToolContext = {
            ...context,
            registry: this,
        };

        // Execute with retry logic
        return await this.executeWithRetry(tool, args, enhancedContext, 0);
    }

    /**
     * Execute tool with retry logic
     */
    private async executeWithRetry(
        tool: ToolDefinition,
        args: any,
        context: ToolContext,
        attempt: number
    ): Promise<ToolResult> {
        try {
            const startTime = Date.now();

            // Execute with timeout
            const result = await Promise.race([
                tool.execute(args, context),
                this.timeout(tool.timeout),
            ]);

            const executionTime = Date.now() - startTime;

            // Reset circuit breaker on success
            this.resetCircuitBreaker(tool.name);

            // Cache result if cacheable
            if (tool.cacheable) {
                await this.setCache(tool.name, args, result);
            }

            logger.debug('Tool executed successfully', {
                tool: tool.name,
                executionTime,
            });

            return {
                success: true,
                data: result,
                executionTimeMs: executionTime,
            };
        } catch (error: any) {
            // Record failure for circuit breaker
            this.recordFailure(tool.name);

            // Retry if attempts remaining
            if (attempt < tool.maxRetries) {
                const delay = Math.pow(2, attempt) * 1000;
                logger.warn('Tool execution failed, retrying', {
                    tool: tool.name,
                    attempt: attempt + 1,
                    maxRetries: tool.maxRetries,
                    delay,
                    error: error.message,
                });
                await this.delay(delay);
                return this.executeWithRetry(tool, args, context, attempt + 1);
            }

            logger.error('Tool execution failed after retries', {
                tool: tool.name,
                error: error.message,
            });

            return {
                success: false,
                error: error.message || 'Tool execution failed',
            };
        }
    }

    /**
     * Check if circuit breaker is open
     */
    private isCircuitOpen(name: string): boolean {
        const state = this.circuitBreaker.get(name);
        if (!state) return false;

        // If circuit is open, check if timeout has passed
        if (state.state === 'open') {
            if (Date.now() - state.lastFailure > this.CIRCUIT_BREAKER_TIMEOUT) {
                // Transition to half-open
                this.circuitBreaker.set(name, {
                    ...state,
                    state: 'half_open',
                });
                return false;
            }
            return true;
        }

        return false;
    }

    /**
     * Record failure for circuit breaker
     */
    private recordFailure(name: string): void {
        const state = this.circuitBreaker.get(name) || {
            failures: 0,
            lastFailure: 0,
            state: 'closed' as const,
        };

        const newState = {
            failures: state.failures + 1,
            lastFailure: Date.now(),
            state: state.state,
        };

        // If failures exceed threshold, open circuit
        if (newState.failures >= this.CIRCUIT_BREAKER_THRESHOLD) {
            newState.state = 'open';
            logger.warn('Circuit breaker opened', { tool: name });
        }

        this.circuitBreaker.set(name, newState);
    }

    /**
     * Reset circuit breaker on success
     */
    private resetCircuitBreaker(name: string): void {
        if (this.circuitBreaker.has(name)) {
            this.circuitBreaker.delete(name);
        }
    }

    /**
     * Get from cache
     */
    private async getFromCache(name: string, args: any): Promise<any | null> {
        try {
            const key = this.getCacheKey(name, args);
            const cached = await redis.get(key);
            if (cached) {
                metrics.cacheHits.labels('redis').inc();
                return JSON.parse(cached);
            } else {
                metrics.cacheMisses.labels('redis').inc();
            }
        } catch (error: any) {
            logger.error('Cache get error', {
                tool: name,
                error: error.message,
            });
            metrics.cacheMisses.labels('redis').inc();
        }
        return null;
    }

    /**
     * Set cache
     */
    private async setCache(name: string, args: any, data: any): Promise<void> {
        try {
            const key = this.getCacheKey(name, args);
            await redis.setex(key, this.CACHE_TTL / 1000, JSON.stringify(data));
        } catch (error: any) {
            logger.error('Cache set error', {
                tool: name,
                error: error.message,
            });
        }
    }

    /**
     * Generate cache key
     */
    private getCacheKey(name: string, args: any): string {
        const argsStr = JSON.stringify(args);
        return `tool:${name}:${this.hash(argsStr)}`;
    }

    /**
     * Simple hash function for cache keys
     */
    private hash(str: string): string {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash;
        }
        return hash.toString(36);
    }

    /**
     * Timeout wrapper
     */
    private timeout(ms: number): Promise<never> {
        return new Promise((_, reject) => {
            setTimeout(() => {
                reject(new Error(`Tool execution timeout after ${ms}ms`));
            }, ms);
        });
    }

    /**
     * Delay helper
     */
    private delay(ms: number): Promise<void> {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Get cache statistics
     */
    async getCacheStats(): Promise<{ keys: number; memory: string; hits: number; misses: number }> {
        try {
            await redis.info('stats');
            const keys = await redis.dbsize();
            const memory = await redis.info('memory');

            // Parse memory usage
            const memoryMatch = memory.match(/used_memory_human:([^\r\n]+)/);
            const memoryUsed = memoryMatch ? memoryMatch[1].trim() : 'unknown';

            return {
                keys: Number(keys),
                memory: memoryUsed,
                hits: 0, // Redis tracks this separately
                misses: 0,
            };
        } catch (error: any) {
            logger.error('Failed to get cache stats:', error);
            return {
                keys: 0,
                memory: '0B',
                hits: 0,
                misses: 0,
            };
        }
    }

    /**
     * Clear cache
     */
    async clearCache(pattern: string = 'tool:*'): Promise<{ cleared: number }> {
        try {
            const keys = await redis.keys(pattern);
            if (keys.length > 0) {
                await redis.del(keys);
            }
            return { cleared: keys.length };
        } catch (error: any) {
            logger.error('Failed to clear cache:', error);
            throw error;
        }
    }
}
