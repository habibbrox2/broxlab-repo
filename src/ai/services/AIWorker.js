/**
 * AI Worker Service
 * 
 * BullMQ worker for AI background tasks:
 * - Content enhancement
 * - RAG indexing
 * - Batch processing
 */

import { Worker, Queue } from 'bullmq';
import { FEATURE_FLAGS, REQUEST_CONFIG } from '../config.js';
import aiRouter from '../AIRouter.js';
import ragEngine from '../RAGEngine.js';
import logger from '../utils/Logger.js';

class AIWorker {
    constructor(connection, options = {}) {
        this.connection = connection;
        this.worker = null;
        this.queueName = options.queueName || 'ai-tasks';
        this.concurrency = options.concurrency || 5;
    }

    /**
     * Initialize the worker
     */
    async initialize() {
        if (!FEATURE_FLAGS.AI_ENABLED) {
            logger.info('AI Worker disabled via feature flag');
            return;
        }

        this.worker = new Worker(
            this.queueName,
            async (job) => this.processJob(job),
            {
                connection: this.connection,
                concurrency: this.concurrency,
                removeOnComplete: { count: 100 },
                removeOnFail: { count: 50 },
            }
        );

        this.worker.on('completed', (job) => {
            logger.debug(`Job ${job.id} completed`, {
                name: job.name,
                progress: job.progress
            });
        });

        this.worker.on('failed', (job, err) => {
            logger.error(`Job ${job.id} failed`, {
                name: job.name,
                error: err.message,
                attempts: job.attemptsMade,
            });
        });

        logger.info('AI Worker initialized', { queue: this.queueName });
    }

    /**
     * Process a job
     */
    async processJob(job) {
        const { name, data } = job;

        logger.info(`Processing job ${job.id}`, { name, data: { ...data, ...redacted(data) } });

        switch (name) {
            case 'enhance-content':
                return await this.enhanceContent(data);

            case 'index-documents':
                return await this.indexDocuments(data);

            case 'query-rag':
                return await this.queryRag(data);

            case 'batch-chat':
                return await this.batchChat(data);

            default:
                throw new Error(`Unknown job type: ${name}`);
        }
    }

    /**
     * Enhance content with AI
     */
    async enhanceContent(data) {
        const { content, style = 'professional', provider, model } = data;

        const messages = [
            {
                role: 'system',
                content: `You are a content enhancer. Enhance the following content with improved clarity, grammar, and style. Keep the original meaning. Style: ${style}.`
            },
            {
                role: 'user',
                content
            }
        ];

        const response = await aiRouter.chat(messages, provider, model, {
            temperature: 0.5,
            maxTokens: REQUEST_CONFIG.maxTokens,
        });

        return {
            original: content,
            enhanced: response.content,
            provider: response.provider,
            model: response.model,
        };
    }

    /**
     * Index documents for RAG
     */
    async indexDocuments(data) {
        const { documents } = data;

        const result = await ragEngine.addDocuments(documents);

        return {
            indexed: result.chunks,
            documents: result.documents,
        };
    }

    /**
     * Query with RAG
     */
    async queryRag(data) {
        const { query, options = {} } = data;

        const result = await ragEngine.query(query, options);

        return {
            response: result.response.content,
            sources: result.sources,
            withoutRAG: result.withoutRAG,
        };
    }

    /**
     * Batch chat processing
     */
    async batchChat(data) {
        const { items } = data;
        const results = [];

        for (const item of items) {
            try {
                const response = await aiRouter.chat(
                    item.messages,
                    item.provider,
                    item.model,
                    item.options
                );

                results.push({
                    id: item.id,
                    success: true,
                    content: response.content,
                });
            } catch (error) {
                results.push({
                    id: item.id,
                    success: false,
                    error: error.message,
                });
            }
        }

        return {
            total: items.length,
            successful: results.filter(r => r.success).length,
            failed: results.filter(r => !r.success).length,
            results,
        };
    }

    /**
     * Close the worker
     */
    async close() {
        if (this.worker) {
            await this.worker.close();
            logger.info('AI Worker closed');
        }
    }
}

/**
 * Create a queue for AI tasks
 */
export function createAIQueue(connection, queueName = 'ai-tasks') {
    return new Queue(queueName, { connection });
}

/**
 * Add job to queue
 */
export async function addAIJob(queue, name, data, options = {}) {
    return await queue.add(name, data, {
        attempts: options.attempts || 3,
        backoff: {
            type: 'exponential',
            delay: 1000,
        },
        ...options,
    });
}

/**
 * Redact sensitive data from logs
 */
function redacted(data) {
    const sensitive = ['apiKey', 'password', 'token', 'secret'];
    const redacted = { ...data };

    for (const key of sensitive) {
        if (redacted[key]) {
            redacted[key] = '[REDACTED]';
        }
    }

    return redacted;
}

export default AIWorker;