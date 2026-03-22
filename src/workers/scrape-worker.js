import { Worker } from 'bullmq';
import redis from '../queue/redis.js';
import { retryQueue, failedQueue, QUEUE_NAMES } from '../queue/queues.js';
import ScraperEngine from '../scraper/engine/ScraperEngine.js';
import { prepareForRag } from '../rag/prepareForRag.js';
import logger from '../utils/scraperLogger.js';

const concurrency = Number(process.env.SCRAPER_WORKER_CONCURRENCY || 3);

const worker = new Worker(QUEUE_NAMES.JOB, async job => {
    const { url, options } = job.data;
    const result = await ScraperEngine.scrape(url, options);
    if (options?.return_html) {
        return result;
    }
    return prepareForRag(result);
}, { connection: redis, concurrency });

worker.on('failed', async (job, err) => {
    logger.warn('Job failed', { jobId: job.id, error: err?.message });
    if (job.attemptsMade >= (job.opts.attempts || 1)) {
        await failedQueue.add('failed', job.data, { removeOnComplete: true });
    } else {
        await retryQueue.add('retry', job.data);
    }
});

worker.on('completed', job => {
    logger.info('Job completed', { jobId: job.id });
});

async function shutdown() {
    logger.info('Worker shutting down');
    await worker.close();
    process.exit(0);
}

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
